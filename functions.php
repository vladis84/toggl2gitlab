<?php

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
