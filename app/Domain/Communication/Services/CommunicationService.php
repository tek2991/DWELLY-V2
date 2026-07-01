<?php

namespace App\Domain\Communication\Services;

use App\Domain\Communication\Models\NotificationTemplate;
use App\Domain\Communication\Models\CommunicationLog;
use App\Domain\Party\Models\Party;
use Illuminate\Support\Facades\Log;

class CommunicationService
{
    public function send(string $eventName, Party $recipient, array $data = []): void
    {
        $templates = NotificationTemplate::where('event_name', $eventName)
            ->where('is_active', true)
            ->get();

        if ($templates->isEmpty()) {
            Log::warning("No active notification templates found for event: {$eventName}");
            return;
        }

        foreach ($templates as $template) {
            $parsedSubject = $this->parseTemplate($template->subject ?? '', $data);
            $parsedBody = $this->parseTemplate($template->body, $data);

            try {
                // Here you would integrate with Twilio/WATI for WhatsApp or Mail facade for Email
                Log::info("Simulating sending {$template->channel} to {$recipient->display_name}", [
                    'subject' => $parsedSubject,
                    'body' => $parsedBody
                ]);

                CommunicationLog::create([
                    'party_id' => $recipient->id,
                    'template_id' => $template->id,
                    'channel' => $template->channel,
                    'recipient' => $template->channel === 'email' ? $recipient->email : $recipient->phone,
                    'subject' => $parsedSubject,
                    'body' => $parsedBody,
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to send communication: {$e->getMessage()}");
                CommunicationLog::create([
                    'party_id' => $recipient->id,
                    'template_id' => $template->id,
                    'channel' => $template->channel,
                    'recipient' => $template->channel === 'email' ? $recipient->email : $recipient->phone,
                    'subject' => $parsedSubject,
                    'body' => $parsedBody,
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }
        }
    }

    private function parseTemplate(string $template, array $data): string
    {
        $parsed = $template;
        foreach ($data as $key => $value) {
            if (is_scalar($value)) {
                $parsed = str_replace('{{' . $key . '}}', $value, $parsed);
            }
        }
        return $parsed;
    }
}
