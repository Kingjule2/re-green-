<?php

namespace App\Services\Restoration;

final class RestorationCostCalculator
{
    /**
     * Calculate explicit low/base/high restoration scenarios.
     *
     * Missing prices stay null. The calculator never treats an unknown market
     * price as zero.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function calculate(array $input): array
    {
        $items = is_array($input['items'] ?? null) ? $input['items'] : [];
        $missing = [];
        $scenarioTotals = ['low' => 0, 'base' => 0, 'high' => 0];
        $scenarioComplete = ['low' => true, 'base' => true, 'high' => true];
        $lineItems = [];

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                $missing[] = "item.{$index}";

                continue;
            }

            $id = (string) ($item['id'] ?? "item-{$index}");
            $quantity = is_numeric($item['quantity'] ?? null) ? (float) $item['quantity'] : null;
            $prices = is_array($item['prices'] ?? null) ? $item['prices'] : [];
            $line = [
                'id' => $id,
                'label' => (string) ($item['label'] ?? $id),
                'quantity' => $quantity,
                'unit' => $item['unit'] ?? null,
                'scenarios' => [],
            ];

            if ($quantity === null || $quantity < 0) {
                foreach (array_keys($scenarioComplete) as $scenario) {
                    $scenarioComplete[$scenario] = false;
                    $missing[] = "{$id}.quantity";
                }
            }

            foreach (array_keys($scenarioTotals) as $scenario) {
                $price = $prices[$scenario] ?? null;
                $line['scenarios'][$scenario] = [
                    'unit_price' => is_numeric($price) && (float) $price >= 0 ? (float) $price : null,
                    'total' => is_numeric($price) && (float) $price >= 0 && $quantity !== null && $quantity >= 0
                        ? $this->money((float) $price * $quantity)
                        : null,
                ];

                if ($line['scenarios'][$scenario]['total'] === null) {
                    $scenarioComplete[$scenario] = false;
                    $missing[] = "{$id}.{$scenario}";
                } else {
                    $scenarioTotals[$scenario] += $line['scenarios'][$scenario]['total'];
                }
            }

            $lineItems[] = $line;
        }

        $scenarios = [];
        foreach (array_keys($scenarioTotals) as $scenario) {
            $scenarios[$scenario] = [
                'total' => $scenarioComplete[$scenario] ? $this->money($scenarioTotals[$scenario]) : null,
                'complete' => $scenarioComplete[$scenario],
            ];
        }

        return [
            'status' => $missing === [] && $items !== [] ? 'complete' : 'incomplete',
            'currency' => (string) ($input['currency'] ?? 'IDR'),
            'items' => $lineItems,
            'scenarios' => $scenarios,
            'missing_prices' => array_values(array_unique($missing)),
            'assumptions' => [
                'prices_are_user_supplied' => true,
                'unknown_prices_are_not_zero' => true,
            ],
        ];
    }

    private function money(float $value): int|float
    {
        return fmod($value, 1.0) === 0.0 ? (int) $value : round($value, 2);
    }
}
