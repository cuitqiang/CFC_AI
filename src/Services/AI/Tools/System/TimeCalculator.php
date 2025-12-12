<?php
declare(strict_types=1);

namespace Services\AI\Tools\System;

use Services\AI\Tools\BaseTool;

/**
 * 时间计算工具
 * 提供时间相关的计算和转换
 */
class TimeCalculator extends BaseTool
{
    public function __construct()
    {
        $this->name = 'time_calculator';
        $this->description = '计算时间、日期差、工作日等';
        $this->parameters = [
            'type' => 'object',
            'properties' => [
                'operation' => [
                    'type' => 'string',
                    'description' => '操作类型',
                    'enum' => ['diff', 'add', 'workdays', 'format', 'current'],
                ],
                'start_date' => [
                    'type' => 'string',
                    'description' => '开始日期（格式：Y-m-d）',
                ],
                'end_date' => [
                    'type' => 'string',
                    'description' => '结束日期（格式：Y-m-d）',
                ],
                'amount' => [
                    'type' => 'integer',
                    'description' => '增加的天数',
                ],
                'unit' => [
                    'type' => 'string',
                    'description' => '时间单位',
                    'enum' => ['days', 'weeks', 'months', 'years'],
                    'default' => 'days',
                ],
                'format' => [
                    'type' => 'string',
                    'description' => '输出格式',
                    'default' => 'Y-m-d H:i:s',
                ],
            ],
            'required' => ['operation'],
        ];
    }

    public function execute(array $arguments): array
    {
        try {
            $this->validateArguments($arguments);

            $operation = $arguments['operation'];

            $result = match ($operation) {
                'current' => $this->getCurrentTime($arguments),
                'diff' => $this->calculateDiff($arguments),
                'add' => $this->addTime($arguments),
                'workdays' => $this->calculateWorkdays($arguments),
                'format' => $this->formatDate($arguments),
                default => throw new \InvalidArgumentException('不支持的操作类型'),
            };

            return $this->success($result);

        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    private function getCurrentTime(array $arguments): array
    {
        $format = $arguments['format'] ?? 'Y-m-d H:i:s';

        return [
            'timestamp' => time(),
            'formatted' => date($format),
            'timezone' => date_default_timezone_get(),
        ];
    }

    private function calculateDiff(array $arguments): array
    {
        $startDate = new \DateTime($arguments['start_date']);
        $endDate = new \DateTime($arguments['end_date']);

        $diff = $startDate->diff($endDate);

        return [
            'days' => $diff->days,
            'months' => $diff->m + ($diff->y * 12),
            'years' => $diff->y,
            'formatted' => $diff->format('%y 年 %m 月 %d 天'),
        ];
    }

    private function addTime(array $arguments): array
    {
        $startDate = new \DateTime($arguments['start_date']);
        $amount = $arguments['amount'];
        $unit = $arguments['unit'] ?? 'days';

        $interval = match ($unit) {
            'days' => "P{$amount}D",
            'weeks' => "P" . ($amount * 7) . "D",
            'months' => "P{$amount}M",
            'years' => "P{$amount}Y",
            default => throw new \InvalidArgumentException('不支持的时间单位'),
        };

        $startDate->add(new \DateInterval($interval));

        return [
            'result' => $startDate->format('Y-m-d'),
            'timestamp' => $startDate->getTimestamp(),
        ];
    }

    private function calculateWorkdays(array $arguments): array
    {
        $startDate = new \DateTime($arguments['start_date']);
        $endDate = new \DateTime($arguments['end_date']);

        $workdays = 0;
        $current = clone $startDate;

        while ($current <= $endDate) {
            $dayOfWeek = (int) $current->format('N');

            // 1-5 是周一到周五（工作日）
            if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
                $workdays++;
            }

            $current->add(new \DateInterval('P1D'));
        }

        return [
            'workdays' => $workdays,
            'total_days' => $startDate->diff($endDate)->days + 1,
        ];
    }

    private function formatDate(array $arguments): array
    {
        $date = new \DateTime($arguments['start_date']);
        $format = $arguments['format'] ?? 'Y-m-d H:i:s';

        return [
            'formatted' => $date->format($format),
            'timestamp' => $date->getTimestamp(),
        ];
    }
}
