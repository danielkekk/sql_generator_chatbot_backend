<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSqlQuery;
use App\Models\LlmUsageLog;
use App\Rules\ValidUuid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\MessageRole;

class SqlController extends Controller
{
    public function ask(Request $request): JsonResponse
    {
        $request->validate([
            'messages'           => ['required', 'array', 'min:1'],
            'messages.*.role'    => ['required', 'string', 'in:human,user,assistant,ai,model,bot'],
            'messages.*.content' => ['required', 'string', 'max:4096'],
        ]);

        $messages = collect($request->input('messages'))
            ->map(fn (array $m) => new Message(
                role: in_array($m['role'], ['human', 'user']) ? MessageRole::User : MessageRole::Assistant,
                content: $m['content'],
            ));

        $last    = $messages->last();
        $history = $messages->take($messages->count() - 1)->values()->all();

        $jobId = Str::uuid()->toString();

        Cache::put("sql_result_{$jobId}", ['status' => 'pending'], now()->addMinutes(10));

        LlmUsageLog::create([
            'user_id'  => $request->user()->id,
            'job_id'   => $jobId,
            'endpoint' => 'sql',
            'status'   => 'pending',
        ]);

        ProcessSqlQuery::dispatch($jobId, $history, $last);

        return response()->json(['status' => 'processing', 'id' => $jobId], 202);
    }

    public function result(string $id): JsonResponse
    {
        $validator = Validator::make(['id' => $id], ['id' => ['required', new ValidUuid]]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first('id')], 400);
        }

        $result = Cache::get("sql_result_{$id}");

        if ($result === null) {
            return response()->json(['error' => 'Result can not be found or expired'], 404);
        }

        $statusCode = match ($result['status']) {
            'pending' => 202,
            'failed'  => 500,
            default   => 200,
        };

        return response()->json($result, $statusCode);
    }
}
