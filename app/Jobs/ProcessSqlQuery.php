<?php

namespace App\Jobs;

use App\Ai\Agents\SQLMaster;
use App\Models\LlmUsageLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Throwable;

class ProcessSqlQuery implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private string $jobId,
        private array $history,
        private Message $last,
    ) {}

    public function handle(): void
    {
        try {
            $agent = new SQLMaster($this->history);
            $response = $agent->prompt($this->last->content);

            $sql = trim($response->text);

            if (empty($sql)) {
                $this->storeResult([
                    'status'     => 'done',
                    'error'      => 'The agent returned no response.',
                    'sql'        => '',
                    'sqlResult'  => [],
                ]);
                $this->updateLog('done', $response);
                return;
            }

            if (!$this->isSQL($sql)) {
                $this->storeResult([
                    'status'      => 'done',
                    'explanation' => $sql,
                    'sql'         => '',
                    'sqlResult'   => [],
                ]);
                $this->updateLog('done', $response);
                return;
            }

            try {
                $sqlResult = DB::select($sql);
            } catch (Throwable $e) {
                $this->storeResult([
                    'status'     => 'done',
                    'error'      => 'Failed to execute the SQL query.',
                    'sql'        => $sql,
                    'sqlResult'  => [],
                ]);
                $this->updateLog('done', $response);
                return;
            }

            if (empty($sqlResult)) {
                $this->storeResult([
                    'status'     => 'done',
                    'error'      => 'The query returned no results.',
                    'sql'        => $sql,
                    'sqlResult'  => [],
                ]);
                $this->updateLog('done', $response);
                return;
            }

            $sqlResultText = json_encode($sqlResult, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $explanationPrompt = "The following is the result of a SQL query:\n{$sqlResultText}\n\nThe query:\n{$sql}\n\nDescribe in simple, human language what this result means based on the query.";

            $explainerHistory = array_merge($this->history, [
                $this->last,
                new AssistantMessage($response->text),
            ]);

            $explanation = (new SQLMaster($explainerHistory))->prompt($explanationPrompt)->text;

            $this->storeResult([
                'status'      => 'done',
                'explanation' => $explanation,
                'sql'         => $sql,
                'sqlResult'   => $sqlResult,
            ]);
            $this->updateLog('done', $response);
        } catch (Throwable $e) {
            $this->storeResult([
                'status' => 'failed',
                'error'  => 'An internal error occurred during processing.',
            ]);
            $this->updateLog('failed');
        }
    }

    private function isSQL(string $text): bool
    {
        return (bool) preg_match(
            '/^\s*(SELECT|INSERT|UPDATE|DELETE|CREATE|DROP|ALTER|WITH)\s/i',
            trim($text)
        );
    }

    private function storeResult(array $data): void
    {
        Cache::put("sql_result_{$this->jobId}", $data, now()->addMinutes(10));
    }

    private function updateLog(string $status, ?object $response = null): void
    {
        LlmUsageLog::where('job_id', $this->jobId)->update([
            'status'        => $status,
            'model'         => $response?->model ?? null,
            'tokens_input'  => $response?->usage?->inputTokens ?? null,
            'tokens_output' => $response?->usage?->outputTokens ?? null,
        ]);
    }
}
