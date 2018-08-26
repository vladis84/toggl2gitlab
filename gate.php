<?php

/**
 * @param string $url
 * @param string $httpMethod
 *
 * @return resource
 */
function initGate(string $url, string $httpMethod)
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $httpMethod);

    return $ch;
}

$sendToggl = function (string $token, string $startDate, string $endDate) use ($config) : array {
    $togglBaseUrl = strtr(
        ':reportUrl?workspace_id=:workspace&since=:dateStart&until=:dateEnd&user_agent=api_test',
        [
            ':reportUrl' => $config['toggl']['reportUrl'],
            ':workspace' => $config['toggl']['workSpace'],
            ':dateStart' => $startDate,
            ':dateEnd'   => $endDate,
        ]
    );

    $ch = initGate($togglBaseUrl, 'GET');

    curl_setopt($ch, CURLOPT_USERPWD, $token . ':api_token');

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        die('Error:' . curl_error($ch));
    }

    curl_close($ch);

    return json_decode($result, true);
};

/**
 * Отправляет запросы в gitlab.
 *
 * @param string $subUrl
 * @param string $method
 *
 * @return array
 */
$sendGitlab = function (string $subUrl, string $method) use ($config): array {
    $gitlabBaseUrl = strtr(
        ':projectUrl/api/v4/projects/:project/',
        [
            ':projectUrl' => rtrim($config['gitlab']['projectUrl'], '/'),
            ':project'    => trim($config['gitlab']['project'], '/'),
        ]
    );

    $fullUrl = $gitlabBaseUrl . $subUrl;
    $ch      = initGate($fullUrl, $method);
    $headers = ['Private-Token: ' . $config['gitlab']['token']];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        die('Error:' . curl_error($ch));
    }
    curl_close($ch);

    return json_decode($result, true);
};
