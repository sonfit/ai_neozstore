<?php

namespace App\Services;

use Joaopaulolndev\FilamentGeneralSettings\Services\GeneralSettingsService;
use OpenAI;

class SummarizeService
{
    public function __construct(
        protected GeneralSettingsService $settingsService
    ) {
    }

    public function summarizeCaseFromItems(array $items, ?int $maxChars = null, ?int $overrideMaxTokens = null): ?string
    {
        $settings = $this->settingsService->get();
        if (!$settings) {
            return null;
        }

        // Accept either flat configs or nested under ChatGPT_configs
        $configs = $settings->more_configs ?? [];
        if (isset($configs['ChatGPT_configs']) && is_array($configs['ChatGPT_configs'])) {
            $configs = array_merge($configs, $configs['ChatGPT_configs']);
        }

        $apiKey = $configs['key_api'] ?? null;
        $model = $configs['model_api'] ?? 'gpt-4o-mini';
        $temperature = isset($configs['temperature_api']) ? (float) $configs['temperature_api'] : 0.2;
        // Raise default tokens to reduce cutoffs
        $maxTokens = isset($configs['max_tokens_api']) ? (int) $configs['max_tokens_api'] : 1500;

        if (!$apiKey) {
            return null;
        }

        $joined = $this->buildJoinedContent($items);

        // If content is very long, compress first then do a final summary (hierarchical)
        if (mb_strlen($joined) > 6000) {
            $chunks = $this->chunkArray($items, 8);
            $partials = [];
            foreach ($chunks as $chunk) {
                $partials[] = $this->summarizeText(
                    $this->buildJoinedContent($chunk),
                    $apiKey,
                    $model,
                    $temperature,
                    min($overrideMaxTokens ? max(1, (int) $overrideMaxTokens) : $maxTokens, 800)
                ) ?? '';
            }
            $joined = implode("\n- ", array_filter($partials));
        }

        $finalMaxTokens = $overrideMaxTokens ? max(1, (int) $overrideMaxTokens) : $maxTokens;
        $final = $this->summarizeText($joined, $apiKey, $model, $temperature, $finalMaxTokens, $maxChars);
        return $final ?: null;
    }

    protected function buildJoinedContent(array $items): string
    {
        $contentBlocks = [];


        foreach ($items as $index => $item) {
            $content = trim((string)($item['sumary'] ?? $item['contents_text'] ?? ''));
            if ($content === '') {
                continue;
            }
            $pics = $item['pic'] ?? [];
            $picsList = [];
            if (is_string($pics)) {
                $decoded = json_decode($pics, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $picsList = (array) $decoded;
                }
            } elseif (is_array($pics)) {
                $picsList = $pics;
            }

            $imagesText = empty($picsList) ? '' : ('\nHình ảnh liên quan: ' . implode(', ', $picsList));
            $contentBlocks[] = sprintf("- Mục %d: %s%s", $index + 1, $content, $imagesText);
        }
        return implode("\n", $contentBlocks);
    }

    protected function chunkArray(array $items, int $chunkSize): array
    {
        $chunkSize = max(1, $chunkSize);
        return array_chunk($items, $chunkSize);
    }

    protected function summarizeText(
        string $text,
        string $apiKey,
        string $model,
        float $temperature,
        int $maxTokens,
        ?int $maxChars = null
    ): ?string {
        $limitNote = $maxChars ? ("Không vượt quá " . $maxChars . " ký tự; nếu gần chạm giới hạn, hãy kết thúc câu cho trọn vẹn. ") : '';

        $prompt = "Bạn là trợ lý tóm tắt tiếng Việt. Hãy viết MỘT đoạn văn tóm tắt hoàn chỉnh, mạch lạc và tự nhiên về nội dung sau, tránh liệt kê khô cứng, không dùng tiêu đề hay mục đánh số. "
            . $limitNote
            . "Chỉ nhắc đến hình ảnh khi thực sự cần để làm rõ ý. Không kết thúc giữa chừng, đảm bảo câu văn trọn vẹn.\n\n"
            . "Nội dung cần tóm tắt:\n" . $text;





        $client = OpenAI::client($apiKey);
        $response = $client->chat()->create([
            'model' => $model,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'messages' => [
                ['role' => 'system', 'content' => 'Bạn là trợ lý tóm tắt bằng tiếng Việt, súc tích, rõ ràng, luôn trả về đoạn văn hoàn chỉnh và tôn trọng giới hạn ký tự đã yêu cầu.'],
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        $text = $response->choices[0]->message->content ?? null;
        if (!$text) {
            return null;
        }

        return $text;
    }
}


