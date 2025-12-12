# CosyVoice2 TTS 集成开发日志

**日期**: 2025年12月12日  
**项目**: CRM_AI_V7 实时语音聊天功能  
**目标**: 实现类似豆包的情感语音合成

---

## 📋 项目背景

用户需求：
- "我想加入聊天有声音，实时的"
- "声音要有情感的，像豆包一样"

最终选择：**CosyVoice2-0.5B** (阿里巴巴开源情感TTS模型)

---

## ✅ 今日成果

### 1. CosyVoice2 环境搭建

| 组件 | 状态 | 说明 |
|------|------|------|
| Git Clone | ✅ | 含 Matcha-TTS 子模块 |
| Conda 环境 | ✅ | `cosyvoice` (Python 3.10) |
| 依赖安装 | ✅ | PyTorch 2.9.1 + CUDA 12.x |
| 模型下载 | ✅ | CosyVoice2-0.5B (~4GB) |

### 2. TTS API 服务器

- **端口**: 5001
- **框架**: FastAPI + Uvicorn
- **功能**: Zero-Shot 语音克隆

```
关键文件:
├── tts_api_server.py    # FastAPI 服务器
├── start_server.sh      # 启动脚本
├── test_model.py        # 模型测试脚本
└── test_api.py          # API 测试脚本
```

### 3. 性能指标

| 指标 | 数值 | 说明 |
|------|------|------|
| 模型加载时间 | ~24秒 | 首次启动 |
| RTF (实时因子) | 0.7-1.0 | 接近实时 |
| 采样率 | 24000 Hz | 高质量音频 |
| 输出格式 | WAV 16-bit | 浏览器兼容 |

---

## ❌ 遇到的问题与解决方案

### 问题 1: huggingface_hub 版本冲突

**错误信息**:
```
cannot import name 'cached_download' from 'huggingface_hub'
```

**原因**: huggingface_hub 新版本移除了 `cached_download` 函数

**解决方案**:
```bash
pip install huggingface_hub==0.23.0
pip install transformers==4.40.0
```

**反思**: 
- 开源项目依赖版本变化快，需要锁定版本
- 查看项目的 requirements.txt 中的版本约束

---

### 问题 2: torchaudio 加载/保存失败

**错误信息**:
```
ImportError: TorchCodec is required for load_with_torchcodec
```

**原因**: torchaudio 新版本默认使用 torchcodec，但未安装

**解决方案**: 使用 `soundfile` 替代
```python
import soundfile as sf

# 读取音频
audio_data, sr = sf.read(audio_path)
prompt_speech = torch.from_numpy(audio_data).float().unsqueeze(0)

# 保存音频
sf.write(output_path, audio_np, sample_rate)
```

**反思**:
- 不要假设库的默认行为不变
- soundfile 是更稳定的音频 I/O 选择

---

### 问题 3: CosyVoice2 API 与 CosyVoice 不同

**错误信息**:
```
CosyVoice.inference_zero_shot() missing 1 required positional argument: 'prompt_speech_16k'
```

**原因**: CosyVoice2 是 Zero-Shot 模型，必须提供参考音频

**CosyVoice vs CosyVoice2 API 对比**:

| 方法 | CosyVoice | CosyVoice2 |
|------|-----------|------------|
| inference_sft | ✅ 内置说话人 | ❌ 无内置说话人 |
| inference_zero_shot | 可选参考音频 | ✅ 必须参考音频 |
| inference_instruct | ✅ 支持 | ❌ 用 instruct2 |
| inference_instruct2 | ❌ | ✅ 支持 |

**正确用法**:
```python
# CosyVoice2 Zero-Shot 模式
for output in model.inference_zero_shot(
    tts_text,           # 要合成的文本
    prompt_text,        # 参考音频的文字内容
    prompt_speech_16k,  # 16kHz 参考音频
    stream=False,
    speed=1.0
):
    audio = output['tts_speech']
```

**反思**:
- 模型版本不同，API 可能完全不同
- 先查看源码或官方文档确认 API 签名

---

### 问题 4: PowerShell 与 WSL 进程管理

**问题**: PowerShell 命令会中断 WSL 后台进程

**失败的方法**:
```powershell
# ❌ Start-Sleep 会发送 Ctrl+C
wsl bash -c "python server.py" 
Start-Sleep -Seconds 30  # 这会中断上面的进程！

# ❌ PowerShell 不支持 &
wsl bash -c "python server.py" &  # 语法错误
```

**成功的方法**:
```powershell
# ✅ 使用 tmux
wsl tmux new-session -d -s tts "bash /path/to/start_server.sh"

# ✅ 检查会话
wsl tmux ls

# ✅ 查看日志
wsl tmux capture-pane -t tts -p | Select-Object -Last 30
```

**反思**:
- PowerShell 与 WSL 进程管理有坑
- tmux 是 WSL 后台运行的最佳选择
- screen 在 WSL 中不太稳定

---

### 问题 5: CUDA/ONNX 警告 (非阻塞)

**警告信息**:
```
Failed to create CUDAExecutionProvider
libcudnn.so.8: cannot open shared object file
```

**原因**: ONNX Runtime 找不到 CUDA 库

**影响**: 仅影响 ONNX 加速，自动回退到 CPU，不影响功能

**可选优化**:
```bash
# 安装 cuDNN (如果需要 GPU 加速 ONNX)
sudo apt install libcudnn8
```

**反思**:
- 区分致命错误和警告
- 不是所有错误都需要立即解决

---

## 📁 文件清单

### 新增文件

```
H:\Desktop\RUST\CosyVoice\
├── tts_api_server.py      # TTS API 服务器 (FastAPI)
├── start_server.sh        # 启动脚本
├── test_model.py          # 模型测试脚本
├── test_api.py            # API 测试脚本
├── test_output.wav        # 测试生成的音频
├── api_test.wav           # API 生成的音频
└── pretrained_models/
    └── CosyVoice2-0.5B/   # 模型文件 (~4GB)
```

### 修改文件

```
H:\Desktop\RUST\CRM_AI_V7\public\
└── index.html             # 添加语音控制 UI (待完成集成)
```

---

## 🔧 启动命令汇总

### 启动 TTS 服务

```powershell
# 方法 1: tmux (推荐)
wsl tmux new-session -d -s tts "bash /mnt/h/Desktop/RUST/CosyVoice/start_server.sh"

# 方法 2: 前台运行 (调试用)
wsl bash /mnt/h/Desktop/RUST/CosyVoice/start_server.sh
```

### 测试 TTS API

```powershell
# 健康检查
wsl curl -s http://localhost:5001/

# 语音合成
wsl bash -c "source ~/miniconda3/etc/profile.d/conda.sh && conda activate cosyvoice && python /mnt/h/Desktop/RUST/CosyVoice/test_api.py"
```

### 管理 tmux 会话

```powershell
wsl tmux ls                              # 列出会话
wsl tmux attach -t tts                   # 进入会话
wsl tmux kill-session -t tts             # 关闭会话
wsl tmux capture-pane -t tts -p          # 查看输出
```

---

## 📚 学到的经验

### 1. 依赖管理
- **锁定版本**: 开源项目依赖变化快，使用 `pip freeze` 记录
- **隔离环境**: Conda 环境隔离是必须的
- **降级策略**: 遇到版本冲突，优先降级到项目推荐版本

### 2. API 兼容性
- **不同版本 API 不同**: CosyVoice vs CosyVoice2 API 完全不同
- **先看源码**: `grep -A 10 "def method_name"` 快速了解签名
- **参考官方示例**: webui.py 是最好的参考

### 3. 跨平台开发
- **PowerShell + WSL 有坑**: 进程管理需要特别注意
- **tmux 是好朋友**: WSL 后台运行首选
- **路径转换**: `/mnt/h/` ↔ `H:\`

### 4. 调试技巧
- **分步验证**: 模型加载 → 单次合成 → API 服务
- **区分错误级别**: 致命错误 vs 警告
- **保留中间产物**: test_output.wav 方便验证

---

## ⏳ 待完成任务

1. **前端集成**: 将 TTS API 集成到 CRM_AI_V7 聊天界面
2. **流式播放**: 实现边生成边播放，降低延迟
3. **多音色支持**: 上传自定义参考音频
4. **GPU 加速**: 配置 CUDA + cuDNN 提升速度
5. **错误处理**: 前端处理 TTS 失败情况

---

## 📊 时间线回顾

| 时间 | 任务 | 耗时 |
|------|------|------|
| 开始 | Git clone + 环境创建 | ~10分钟 |
| - | 依赖安装 | ~15分钟 |
| - | 模型下载 | ~20分钟 |
| - | 版本冲突调试 | ~30分钟 |
| - | API 调用调试 | ~20分钟 |
| - | 服务器启动调试 | ~15分钟 |
| 完成 | API 测试成功 | - |

**总耗时**: 约 2 小时

---

## 💡 给未来的自己

1. **CosyVoice2 需要参考音频** - 没有内置说话人
2. **tmux 管理 WSL 后台进程** - 不要用 PowerShell &
3. **soundfile 替代 torchaudio** - 更稳定
4. **huggingface_hub ≤ 0.23.0** - 新版本不兼容
5. **先测试模型再写服务** - 确认 API 正确

---

*文档生成时间: 2025-12-12*
