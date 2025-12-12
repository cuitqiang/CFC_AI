<?php
declare(strict_types=1);

namespace Services\AI\Tools\Business;

use Services\AI\Tools\BaseTool;

/**
 * 邮件发送工具
 * 允许 AI 发送邮件通知
 */
class EmailSender extends BaseTool
{
    private ?object $mailer;

    public function __construct(?object $mailer = null)
    {
        $this->mailer = $mailer;

        $this->name = 'email_sender';
        $this->description = '发送邮件通知';
        $this->parameters = [
            'type' => 'object',
            'properties' => [
                'to' => [
                    'type' => 'string',
                    'description' => '收件人邮箱',
                ],
                'subject' => [
                    'type' => 'string',
                    'description' => '邮件主题',
                ],
                'body' => [
                    'type' => 'string',
                    'description' => '邮件正文',
                ],
                'cc' => [
                    'type' => 'array',
                    'description' => '抄送邮箱列表',
                    'items' => ['type' => 'string'],
                ],
                'attachments' => [
                    'type' => 'array',
                    'description' => '附件路径列表',
                    'items' => ['type' => 'string'],
                ],
                'priority' => [
                    'type' => 'string',
                    'description' => '优先级',
                    'enum' => ['low', 'normal', 'high'],
                    'default' => 'normal',
                ],
            ],
            'required' => ['to', 'subject', 'body'],
        ];
    }

    public function execute(array $arguments): array
    {
        try {
            $this->validateArguments($arguments);

            $to = $arguments['to'];
            $subject = $arguments['subject'];
            $body = $arguments['body'];
            $cc = $arguments['cc'] ?? [];
            $attachments = $arguments['attachments'] ?? [];
            $priority = $arguments['priority'] ?? 'normal';

            // 安全检查：防止发送到外部邮箱（可选）
            if (!$this->isAllowedRecipient($to)) {
                return $this->error('不允许发送邮件到该地址');
            }

            // 发送邮件
            $result = $this->sendEmail($to, $subject, $body, $cc, $attachments, $priority);

            if ($result) {
                return $this->success(
                    ['sent' => true, 'to' => $to],
                    '邮件发送成功'
                );
            } else {
                return $this->error('邮件发送失败');
            }

        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    private function isAllowedRecipient(string $email): bool
    {
        // TODO: 实际项目中应检查是否允许发送到该邮箱
        // 例如：只允许发送到公司内部邮箱

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function sendEmail(
        string $to,
        string $subject,
        string $body,
        array $cc,
        array $attachments,
        string $priority
    ): bool {
        // TODO: 实际项目中应使用真实的邮件系统（如 PHPMailer, SwiftMailer 等）

        if ($this->mailer === null) {
            throw new \RuntimeException('邮件服务未配置');
        }

        // 模拟发送
        error_log("发送邮件: {$to} - {$subject}");

        return true;
    }
}
