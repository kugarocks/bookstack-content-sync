# 方案：受控的宿主真实写入型 push execute 验证

## 背景

当前扩展包已经完成：

- pull / push 功能迁移
- 本地 path repository 宿主安装验证
- 最小 pull 实跑
- push plan 验证
- no-change `push --execute` 验证
- 命名空间从 `KugaRocks` 统一到 `Kugarocks`

现在还缺少一次更明确的“真实写入路径”宿主验证，用来支撑首个版本发布前的信心。

## 当前观察

在当前宿主仓库 `BookStack` 的 `slug` 分支中：

- 工作区是干净的
- 本地验证提交仍在：`e9581c8d5`
- 宿主 `composer.json` 仍通过 path repository 引入本地包
- 但当前执行 `php artisan list` 未直接列出 sync 命令

这通常意味着在最近的命名空间调整后，宿主侧需要重新执行 Composer 自动加载与包发现。

## 目标

- 恢复宿主对当前本地包版本的正确识别
- 再次确认 `bookstack:pull-content` / `bookstack:push-content` 命令可用
- 设计一个受控、最小范围、可回滚理解的真实写入型 `push --execute` 验证
- 记录验证结果与风险边界

## 推荐执行顺序

### Phase 12.1：恢复宿主集成状态

在宿主仓库中执行：

- `composer update kugarocks/bookstack-content-sync --no-interaction`
- 或在必要时至少执行 `composer dump-autoload` 并触发 package discovery

然后检查：

- `php artisan bookstack:pull-content --help`
- `php artisan bookstack:push-content --help`

### Phase 12.2：先做 plan，不直接 execute

选择一个受控项目目录，先运行：

- `php artisan bookstack:push-content <project>`

要求：

- plan 输出必须清楚、改动范围可理解
- 优先选择只会创建或更新少量内容的场景
- 避免对重要现有生产数据做大范围改动

### Phase 12.3：再决定是否执行真实写入

只有在以下条件同时满足时才执行：

- 命令接入正常
- plan 可解释
- 改动范围小
- 目标数据是可接受验证的测试内容或低风险内容

执行命令：

- `php artisan bookstack:push-content <project> --execute`

然后验证：

- 远端 BookStack 中目标内容是否符合预期
- 本地 `snapshot.json` / 文件中的 `entity_id` 是否正确回写
- 再跑一次 plan，确认回到 no-change 或预期稳定状态

## 受控原则

- 不在未知影响范围的数据集上直接 execute
- 优先使用单一 shelf / book / page 的小范围场景
- 如果 plan 显示大量 rename / trash / membership 变化，应立即停止
- 验证重点是“写入路径是否可用”，不是一次性覆盖所有复杂场景

## 风险点

### 1. 宿主环境中的真实写入不可完全自动回滚

因此必须先看 plan，再 execute。

### 2. 当前宿主命令未显示，需先恢复集成

如果宿主在命名空间变更后仍未恢复识别，必须先解决集成问题，再谈真实写入验证。

## 预期结果

- 宿主再次成功识别本地包命令
- 至少完成一次受控 plan 验证
- 如条件合适，完成一次最小真实写入型 `push --execute` 验证
- 把结果回写到发布前文档与进度中

## 结论

- 状态：执行中
- 当前推荐：先恢复宿主集成状态，再决定 execute 范围
