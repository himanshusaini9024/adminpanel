<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppBulkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<int, int>  $customerIds
     */
    public function __construct(
        public array $customerIds,
        public string $message,
        public ?string $headerImage = null,
    ) {}

    public function handle(WhatsAppService $whatsapp): void
    {
        $ids = array_values(array_unique(array_map('intval', $this->customerIds)));
        $sent = 0;
        $failed = 0;
        $skipped = 0;

        Log::info('SendWhatsAppBulkJob start', [
            'count' => count($ids),
            'has_header_override' => filled($this->headerImage),
        ]);

        foreach ($ids as $index => $customerId) {
            $customer = Customer::query()
                ->where('customer_id', $customerId)
                ->first(['customer_id', 'first_name', 'last_name', 'phone']);

            if (!$customer || empty($customer->phone)) {
                $skipped++;
                continue;
            }

            $name = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')) ?: 'Customer';

            try {
                $result = $whatsapp->sendCustomerOutreach(
                    $customer->phone,
                    $name,
                    $this->message,
                    $this->headerImage
                );

                if ($result['ok']) {
                    $sent++;
                } else {
                    $failed++;
                    Log::warning('SendWhatsAppBulkJob: send failed', [
                        'customer_id' => $customerId,
                        'message' => $result['message'],
                    ]);
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::error('SendWhatsAppBulkJob: exception', [
                    'customer_id' => $customerId,
                    'error' => $e->getMessage(),
                ]);
            }

            // Soft rate-limit (marketing templates)
            if ($index < count($ids) - 1) {
                usleep(250000);
            }
        }

        Log::info('SendWhatsAppBulkJob done', [
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
        ]);
    }
}
