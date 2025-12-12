<?php
declare(strict_types=1);

namespace Services\AI\Prompts;

/**
 * 提示词模板管理器
 * 集中管理所有 AI 提示词模板
 * 
 * 功能：
 * - 加载模板文件
 * - 变量替换
 * - 模板缓存
 * - 多语言支持
 */
class TemplateManager
{
    private array $templates = [];
    private array $cache = [];
    private string $templateDir;
    private string $defaultLocale = 'zh-CN';

    /**
     * 构造函数
     * 
     * @param string|null $templateDir 模板目录路径
     */
    public function __construct(?string $templateDir = null)
    {
        $this->templateDir = $templateDir ?? dirname(__DIR__, 4) . '/config/prompts';
        $this->loadBuiltinTemplates();
    }

    /**
     * 获取模板内容（带变量替换）
     * 
     * @param string $name 模板名称
     * @param array $variables 变量数组
     * @return string 渲染后的模板
     */
    public function get(string $name, array $variables = []): string
    {
        // 检查缓存
        $cacheKey = $name . ':' . md5(serialize($variables));
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        // 获取原始模板
        $template = $this->getRaw($name);
        if ($template === null) {
            throw new \InvalidArgumentException("模板不存在: {$name}");
        }

        // 变量替换
        $rendered = $this->render($template, $variables);

        // 缓存
        $this->cache[$cacheKey] = $rendered;

        return $rendered;
    }

    /**
     * 获取原始模板（不进行变量替换）
     * 
     * @param string $name 模板名称
     * @return string|null
     */
    public function getRaw(string $name): ?string
    {
        return $this->templates[$name] ?? null;
    }

    /**
     * 注册模板
     * 
     * @param string $name 模板名称
     * @param string $content 模板内容
     * @return self
     */
    public function register(string $name, string $content): self
    {
        $this->templates[$name] = $content;
        return $this;
    }

    /**
     * 批量注册模板
     * 
     * @param array $templates [name => content, ...]
     * @return self
     */
    public function registerMany(array $templates): self
    {
        foreach ($templates as $name => $content) {
            $this->register($name, $content);
        }
        return $this;
    }

    /**
     * 从文件加载模板
     * 
     * @param string $name 模板名称
     * @param string $filename 文件名
     * @return self
     */
    public function loadFromFile(string $name, string $filename): self
    {
        $path = $this->templateDir . '/' . $filename;
        
        if (!file_exists($path)) {
            throw new \RuntimeException("模板文件不存在: {$path}");
        }

        $this->templates[$name] = file_get_contents($path);
        return $this;
    }

    /**
     * 检查模板是否存在
     * 
     * @param string $name 模板名称
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->templates[$name]);
    }

    /**
     * 获取所有模板名称
     * 
     * @return array
     */
    public function all(): array
    {
        return array_keys($this->templates);
    }

    /**
     * 渲染模板（变量替换）
     * 
     * @param string $template 模板内容
     * @param array $variables 变量数组
     * @return string
     */
    private function render(string $template, array $variables): string
    {
        // 支持 {{variable}} 和 {variable} 两种语法
        foreach ($variables as $key => $value) {
            if (is_scalar($value)) {
                $template = str_replace(
                    ["{{$key}}", "{{{$key}}}"],
                    (string) $value,
                    $template
                );
            }
        }

        return $template;
    }

    /**
     * 清除缓存
     * 
     * @return self
     */
    public function clearCache(): self
    {
        $this->cache = [];
        return $this;
    }

    /**
     * 加载内置模板
     */
    private function loadBuiltinTemplates(): void
    {
        $this->templates = [
            // ========== 辩论系统模板 ==========
            'debate.pro' => <<<'PROMPT'
你是一个专业的辩论家，负责从正面角度论证。
辩题: {topic}

任务：
1. 提出3个有力的支持论据
2. 用具体例子支持观点
3. 控制在150字以内
4. 用中文回答
PROMPT,

            'debate.con' => <<<'PROMPT'
你是一个专业的辩论家，负责从反面角度质疑。
辩题: {topic}

任务：
1. 提出3个有力的反对论据
2. 指出正方观点的问题
3. 控制在150字以内
4. 用中文回答
PROMPT,

            'debate.neutral' => <<<'PROMPT'
你是客观中立的分析师。
辩题: {topic}

任务：
1. 分析正反双方观点
2. 指出各方优劣
3. 控制在150字以内
4. 用中文回答
PROMPT,

            'debate.summary' => <<<'PROMPT'
你是辩论总结专家。请综合以下讨论内容：

辩题: {topic}

各方观点:
{context}

请综合总结：
1. 各方核心观点
2. 不同视角价值
3. 建设性结论

（200字以内，中文）
PROMPT,

            // ========== 通用任务模板 ==========
            'task.contract_review' => <<<'PROMPT'
你是一位专业的合同审查专家。请审查以下合同条款：

{contract_content}

请从以下角度分析：
1. 风险条款识别
2. 权责是否对等
3. 潜在法律风险
4. 修改建议
PROMPT,

            'task.worktime_estimate' => <<<'PROMPT'
你是一位资深项目经理。请评估以下任务的工时：

任务描述: {task_description}
项目类型: {project_type}
技术栈: {tech_stack}

请提供：
1. 工时估算（人天）
2. 评估依据
3. 风险因素
4. 优化建议
PROMPT,

            // ========== 系统模板 ==========
            'system.safety_check' => <<<'PROMPT'
检查以下内容是否包含不当内容（色情、暴力、政治敏感等）：

{content}

返回 JSON 格式：{"safe": true/false, "reason": "原因"}
PROMPT,

            'system.json_output' => <<<'PROMPT'
{prompt}

请以 JSON 格式返回结果，不要包含任何其他内容。
PROMPT,
        ];
    }
}
