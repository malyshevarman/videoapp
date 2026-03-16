<?php

namespace App\Http\Controllers;

use App\Models\ServiceOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ServiceShareController extends Controller
{
    public function sendClientEmail(ServiceOrder $service): JsonResponse
    {
        $recipientEmail = trim((string) data_get($service->client, 'customerEmail', ''));

        if ($recipientEmail === '') {
            return response()->json([
                'message' => 'Email клиента не найден.',
            ], 422);
        }

        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'message' => 'Email клиента указан в неверном формате.',
            ], 422);
        }

        $link = $this->buildServiceLink($service);
        $messageLines = [
            'Уважаемый клиент, отправляем вам ссылку на видео-обзор сервиса.',
            '',
            $link,
        ];

        Mail::raw(implode(PHP_EOL, $messageLines), function ($message) use ($recipientEmail, $service) {
            $orderNumber = data_get($service->referenceObject, 'orderId', $service->order_id ?: $service->id);

            $message
                ->to($recipientEmail)
                ->subject("Ссылка на видео-обзор сервиса по заявке №{$orderNumber}");
        });

        $this->markApprovalLinkSent($service);

        return response()->json([
            'success' => true,
        ]);
    }

    public function sendClientSms(ServiceOrder $service): JsonResponse
    {
        $recipientPhone = $this->normalizePhone((string) data_get($service->client, 'customerPhone', ''));

        if ($recipientPhone === null) {
            return response()->json([
                'message' => 'Телефон клиента не найден или указан в неверном формате.',
            ], 422);
        }

        $smsConfig = config('services.beeline_sms');
        $message = 'Уважаемый клиент, отправляем вам ссылку на видео-обзор сервиса ' . $this->buildServiceLink($service);

        if (empty($smsConfig['url']) || empty($smsConfig['user']) || empty($smsConfig['pass']) || empty($smsConfig['sender'])) {
            return response()->json([
                'message' => 'SMS-провайдер не настроен.',
            ], 500);
        }

        $response = Http::timeout(15)->get($smsConfig['url'], [
            'user' => $smsConfig['user'],
            'pass' => $smsConfig['pass'],
            'action' => $smsConfig['action'] ?: 'post_sms',
            'message' => $message,
            'target' => $recipientPhone,
            'sender' => $smsConfig['sender'],
        ]);
     /*   dd([
            'status' => $response->status(),
            'body' => $response->body(),
        ]);
     */
        if (!$response->successful()) {
            return response()->json([
                'message' => 'Не удалось отправить SMS клиенту.',
            ], 502);
        }

        $this->markApprovalLinkSent($service);

        return response()->json([
            'success' => true,
        ]);
    }

    private function buildServiceLink(ServiceOrder $service): string
    {
        return url("/{$service->public_url}/");
    }

    private function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 11 && in_array($digits[0], ['7', '8'], true)) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) !== 10) {
            return null;
        }

        return '7' . $digits;
    }

    private function markApprovalLinkSent(ServiceOrder $service): void
    {
        $records = $service->processStatusRecords ?? [];
        if (!is_array($records)) {
            $records = [];
        }

        $exists = collect($records)->contains(
            fn ($record) => ($record['status'] ?? null) === 'approvalLinkSent'
        );

        if ($exists) {
            return;
        }

        $records[] = [
            'id' => (string) Str::uuid(),
            'status' => 'approvalLinkSent',
            'timestamp' => now()->toISOString(),
        ];

        $service->processStatusRecords = $records;
        $service->processStatus = 'approvalLinkSent';
        $service->save();
    }
}
