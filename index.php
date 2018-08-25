<?php

$config = require 'config.php';

$startDate = null;
$endDate   = null;

while ($param = array_shift($argv)) {
    [$paramName, $paramValue] = explode('=', $param) + ['', ''];
    switch ($paramName) {
        case '--start-date':
            $startDate = $paramValue;
            break;

        case '--end-date':
            $endDate = $paramValue;
            break;
    }
}

if (!$startDate && !$endDate) {
    die('Параметры --start-date и --end-date обязательны для заполнения');
}

$togglBaseUrl = strtr(
    'https://toggl.com/reports/api/v2/summary?workspace_id=:workspace&since=:dateStart&until=:dateEnd&user_agent=api_test',
    [
        ':workspace' => $config['toggl']['workSpace'],
        ':dateStart' => $startDate,
        ':dateEnd'   => $endDate,
    ]
);

$getTogglTasks = function ($token) use ($togglBaseUrl) {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $togglBaseUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

    curl_setopt($ch, CURLOPT_USERPWD, $token . ':api_token');

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        die('Error:' . curl_error($ch));
    }

    curl_close($ch);

    return json_decode($result, true);
};

$gitlabBaseUrl = strtr(
    ':projectUrl/api/v4/projects/:project/',
    [
        ':projectUrl' => rtrim($config['gitlab']['projectUrl'], '/'),
        ':project'    => trim($config['gitlab']['project'], '/'),
    ]
);

$sendGitlab = function ($url, $method) use ($gitlabBaseUrl, $config) {
    $ch = curl_init();

    $fullUrl = $gitlabBaseUrl . $url;

    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    $headers = ['Private-Token: ' . $config['gitlab']['token']];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        die('Error:' . curl_error($ch));
    }
    curl_close($ch);

    return json_decode($result, true);
};

$tasks = [];
foreach ($config['toggl']['tokens'] as $togglToken) {
    $result = $getTogglTasks($togglToken, $startDate, $endDate);

    $data = $result['data'] ?? [];
    foreach ($data as $project) {
        foreach ($project['items'] as $task) {
            // Ищем номер задачи
            if (preg_match('/#(\d+)/', $task['title']['time_entry'], $matches)) {
                echo "Найдена задача #{$matches[1]} в toggl\n";
                $tasks[$matches[1]] = $task['time'] / 1000;
            }
        }
    }
}

if (empty($tasks)) {
    return;
}

$issueIds = array_keys($tasks);
$url      = '/issues?iids[]=' . join('&iids[]=', $issueIds);
$issues   = $sendGitlab($url, 'GET');
$issues   = $issues ?: [];
foreach ($issues as $issue) {
    $issueId    = $issue['iid'];
    $issueSpent = $issue['time_stats']['total_time_spent'];
    echo "Найдена задача #{$issueId} в gitlab\n";

    $message = "Задача #{$issueId} не обновлена\n";
    if ($tasks[$issueId] > $issueSpent) {
        $spent = $tasks[$issueId] - $issueSpent;
        $url   = "/issues/{$issueId}/add_spent_time?duration={$spent}s";
        $sendGitlab($url, 'POST');
        $message = "В задачу #{$issueId} добавлено {$spent} sec\n";
    }

    echo $message;
}
