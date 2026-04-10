# 方案：代码迁移边界

## 背景

当前仓库已经具备最小 Composer 包骨架，但真实的 sync 功能还在原始 BookStack 仓库中。

在正式迁移代码之前，需要先明确迁移边界，否则容易出现以下问题：

- 一次性迁移过多文件，导致问题难以定位
- 迁移顺序不清晰，造成 provider、命令、服务依赖不完整
- 过早处理高风险耦合点，影响整体推进节奏
- 缺少“最小可工作链路”定义，导致阶段目标模糊

因此，这一阶段的目标是明确“先迁什么、后迁什么、不迁什么”。

## 目标

- 确定第一轮代码迁移范围
- 确定暂不迁移的部分
- 定义最小可工作链路
- 为下一步的实际搬运和适配提供边界

## 候选迁移范围

### 方案 A：先迁核心领域逻辑，再接命令

第一轮迁移：

- `ContentSync/Shared/*`
- `ContentSync/Pull/*`
- `ContentSync/Push/*`
- 保留当前包内 command skeleton，第二轮再替换成真实实现

特点：

- 先把大部分业务类搬过来
- 再让命令改为调用这些服务

优点：

- 核心逻辑先落位，整体结构完整
- 后续命令接入时会更顺

缺点：

- 第一轮改动量偏大
- 如果迁移后命令尚未接通，阶段成果不够直观

### 方案 B：按执行链路最短路径迁移

第一轮迁移只覆盖 pull 命令的最小工作链路：

- `Console/Commands/PullContentCommand` 的真实实现替换
- `ContentSync/Pull/*`
- `ContentSync/Shared/*`
- pull 所需的最小依赖适配

第二轮再迁移 push 相关：

- `ContentSync/Push/*`
- `PushContentCommand` 真实实现

特点：

- 先打通 pull，再处理 push
- 按能力逐步恢复功能

优点：

- 节奏最清晰
- 更容易验证阶段成果
- pull 本身通常比 push 风险更低，适合作为第一条打通链路

缺点：

- 中间阶段只有一半能力恢复
- 需要明确区分 pull 和 push 的迁移节奏

### 方案 C：一次性迁移全部 sync 代码

第一轮直接迁移：

- `ContentSync/Shared/*`
- `ContentSync/Pull/*`
- `ContentSync/Push/*`
- 两个 command 的真实实现
- 相关 provider 绑定

优点：

- 一次到位
- 后续阶段看起来更少

缺点：

- 风险最大
- 调试复杂度高
- 不符合当前“阶段性提交、阶段性验证”的节奏

## 推荐方案

推荐采用方案 B。

推荐理由：

- 当前项目已经明确要求分阶段推进和提交
- pull 链路相对更适合作为第一条恢复的真实能力
- 先打通 pull，可以验证：命令注册、provider、生效、远端 API 调用、本地文件输出
- push 逻辑更复杂，包含 plan、diff、execute，本身更适合放在第二轮

## 建议迁移顺序

### Phase 3.1：恢复 pull 链路

建议迁移：

- `ContentSync/Shared/*`
- `ContentSync/Pull/*`
- `PullContentCommand` 真实实现
- provider 中与 pull 相关的绑定

阶段目标：

- 扩展包安装后，`php artisan bookstack:pull-content {projectPath}` 可以真实执行

### Phase 3.2：恢复 push plan / execute 链路

建议迁移：

- `ContentSync/Push/*`
- `PushContentCommand` 真实实现
- provider 中与 push 相关的绑定

阶段目标：

- 扩展包安装后，`bookstack:push-content` 和 `--execute` 可以真实执行

## 暂不迁移内容

第一轮明确不处理：

- Web UI
- 配置发布命令
- 独立 CLI
- 与宿主无关的额外适配层抽象
- 过度重构目录结构

## 宿主依赖处理策略

第一轮允许继续依赖：

- `BookStack\Http\HttpRequestService`
- Laravel container / ServiceProvider
- `Illuminate\Support\Str`
- `Illuminate\Support\Arr`

理由：

- 当前目标是先把功能外移，而不是完全去耦合
- 保留宿主依赖可以显著降低迁移成本

## 最小可工作链路定义

第一阶段最小成功标准：

1. 在 BookStack 宿主中通过 path repository 安装本包
2. provider 自动注册成功
3. `php artisan bookstack:pull-content {projectPath}` 可见且可运行
4. pull 能真实读取 `sync.json`
5. pull 能真实调用远端 API 并写出本地项目文件

第二阶段成功标准：

1. `php artisan bookstack:push-content {projectPath}` 可真实生成计划
2. `php artisan bookstack:push-content {projectPath} --execute` 可真实执行
3. 本地 metadata 与 snapshot 可被正确回写

## 待确认点

1. 是否接受“先 pull、后 push”的迁移顺序
2. 是否接受第一轮继续保留 BookStack 内部服务依赖
3. 第一轮是否只追求 pull 链路跑通，不同时处理 push
4. 是否在每一轮迁移后都做一次英文提交

## 结论

- 状态：待确认
- 当前推荐：方案 B
