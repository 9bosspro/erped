<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * JiraClient — HTTP client สำหรับ Jira REST API v2
 *
 * ใช้ Basic Authentication (username + API token)
 *
 * ตัวอย่าง:
 * ```php
 * $client = $jira->connect('https://your-org.atlassian.net', 'user@example.com', 'api-token');
 * $tickets = $jira->getTicketsByProject($client, ['PROJ', 'DEV']);
 * ```
 */
final class JiraClient
{
    /**
     * สร้าง Guzzle client พร้อม Basic Auth header สำหรับ Jira
     *
     * @param  string  $host  Jira base URL เช่น 'https://your-org.atlassian.net'
     * @param  string  $username  Jira username หรือ email
     * @param  string  $token  Jira API token
     */
    public function connect(string $host, string $username, string $token): Client
    {
        return new Client([
            'base_uri' => $host,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Basic '.base64_encode("{$username}:{$token}"),
            ],
        ]);
    }

    /**
     * ดึง tickets ทั้งหมดในแต่ละ project
     *
     * @param  Client  $client  Guzzle client จาก connect()
     * @param  array<string>  $projectKeys  รายการ project keys เช่น ['PROJ', 'DEV']
     * @return array<string, array{total: int, issues: array}>|null
     */
    public function getTicketsByProject(Client $client, array $projectKeys): ?array
    {
        try {
            $results = [];

            foreach ($projectKeys as $projectKey) {
                $response = $client->get('/rest/api/2/search', [
                    'query' => ['jql' => "project={$projectKey}"],
                ]);

                $data = json_decode($response->getBody()->getContents());

                $results[$projectKey] = [
                    'total' => $data->total,
                    'issues' => array_map(
                        fn ($issue) => [
                            'code' => $issue->key,
                            'name' => $issue->fields->summary,
                            'data' => $issue,
                        ],
                        $data->issues,
                    ),
                ];
            }

            return $results;
        } catch (GuzzleException $e) {
            Log::error('Jira getTicketsByProject failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * ดึงรายละเอียด ticket จาก URL หรือ issue key
     *
     * @param  Client  $client  Guzzle client จาก connect()
     * @param  string  $issueKeyOrUrl  issue key (เช่น 'PROJ-123') หรือ URL เต็ม
     * @return object|null decoded JSON response หรือ null ถ้าเกิด error
     */
    public function getTicketDetails(Client $client, string $issueKeyOrUrl): ?object
    {
        try {
            // รองรับทั้ง URL เต็ม และ issue key โดยตรง
            $parts = explode('/', $issueKeyOrUrl);
            $issueKey = end($parts);

            $response = $client->get("/rest/api/2/issue/{$issueKey}");

            return json_decode($response->getBody()->getContents());
        } catch (GuzzleException $e) {
            Log::error('Jira getTicketDetails failed', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
