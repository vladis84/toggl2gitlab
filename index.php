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

require 'gates.php';

/**
 * Ищет issues по id.
 *
 * @param int[] $issueIds
 *
 * @return array
 */
$getGitlabIssues = function (array $issueIds) use ($sendGitlab): array {
    $url = '/issues?iids[]=' . join('&iids[]=', $issueIds);

    return $sendGitlab($url, 'GET');
};

/**
 * Добавляет потраченное время в задачу.
 *
 * @param int $issueId
 * @param int $spent
 */
$updateIssueSpent = function (int $issueId, int $spent) use ($sendGitlab) {
    $url = "/issues/{$issueId}/add_spent_time?duration={$spent}s";
    $sendGitlab($url, 'POST');
};


$timeSpent = [];
foreach ($config['toggl']['tokens'] as $token) {
    $result = $sendToggl($token, $startDate, $endDate);

    $data = $result['data'] ?? [];
    foreach ($data as $project) {
        foreach ($project['items'] as $task) {
            // Ищем номер задачи
            if (preg_match('/#(\d+)/', $task['title']['time_entry'], $matches)) {
                echo "Найдена задача #{$matches[1]} в toggl\n";
                $timeSpent[$matches[1]] = $task['time'] / 1000;
            }
        }
    }
}

if (empty($timeSpent)) {
    die("Не найдено задач\n");
}

$issueIds = array_keys($timeSpent);
$issues   = $getGitlabIssues($issueIds);
foreach ($issues as $issue) {
    $issueId    = $issue['iid'];
    $issueSpent = $issue['time_stats']['total_time_spent'];
    echo "Найдена задача #{$issueId} в gitlab\n";

    $message = "Задача #{$issueId} не обновлена\n";
    $spent = $timeSpent[$issueId];
    if ($spent > 0) {
        $updateIssueSpent($issueId, $spent);
        $message = "В задачу #{$issueId} добавлено {$spent} sec\n";
    }

    echo $message;
}
