# 方案：Push 迁移与验证

## 背景

当前外部扩展包已经完成以下阶段：

- Composer 骨架建立
- shared / pull 代码迁移
- pull 命令真实接管
- 宿主环境中的最小 pull 实跑成功

这说明当前包已经具备可工作的 pull 能力。接下来需要补齐的是 push 能力，包括：

- push plan 生成
- push execute 执行
- 本地 metadata / snapshot 回写

## 目标

- 迁移 `ContentSync/Push/*`
- 将 `PushContentCommand` 替换为真实实现
- 在宿主中验证 `bookstack:push-content` 至少能生成 plan
- 记录 push execute 是否立即验证，还是延后到下一阶段

## 推荐实施顺序

### Phase 6.1：迁移 push 代码

建议迁移：

- `ContentSync/Push/*`
- `PushContentCommand` 真实实现
- provider 中 push 所需绑定（如有）

目标：

- 扩展包具备与原仓库一致的 push 代码结构
- `bookstack:push-content` 不再是 skeleton

### Phase 6.2：验证 push plan

建议先验证：

- `php artisan bookstack:push-content {projectPath}`

输入目录建议直接复用：

- `/tmp/bookstack-content-sync-smoke`

目标：

- 确认 push plan 能读取当前 pull 导出的本地项目
- 确认计划生成链路可运行

### Phase 6.3：决定是否验证 push execute

只有在 push plan 通过后，再决定是否立即执行：

- `php artisan bookstack:push-content {projectPath} --execute`

## 推荐策略

### 方案 A

先迁移 push 代码并只验证 push plan，不立即做 execute。

优点：

- 风险更低
- 更适合当前分阶段推进方式
- 可以先确认扫描、匹配、diff、plan 构建链路

缺点：

- execute 的问题会延后暴露

### 方案 B

迁移 push 代码后，同时验证 push plan 和 push execute。

优点：

- 一轮内获得更完整结果

缺点：

- 风险更高
- 如果 execute 出错，排查复杂度更高
- 可能直接修改远端数据

## 推荐方案

推荐采用方案 A。

原因：

- push execute 具有真实写操作风险
- 当前更适合先验证 plan 链路
- 一旦 plan 成功，再根据结果决定 execute 是否进入下一轮

## 风险点

### 1. push 依赖比 pull 更复杂

push 依赖本地扫描、snapshot 匹配、结构 diff、内容 diff、远端执行器和本地回写器，问题面更广。

### 2. 与现有测试目录的状态相关

如果 `/tmp/bookstack-content-sync-smoke` 被手工修改或状态不一致，plan 结果会受影响。

### 3. execute 有真实副作用

如果直接执行 `--execute`，可能影响当前本地 BookStack 数据。

## 成功标准

### 本阶段成功

- `ContentSync/Push/*` 已迁移进入扩展包
- `PushContentCommand` 已替换为真实实现
- 宿主中的 `php artisan bookstack:push-content {projectPath}` 能成功生成 plan

### 下一阶段成功

- `php artisan bookstack:push-content {projectPath} --execute` 能在受控条件下成功执行

## 待确认点

1. 是否直接复用 `/tmp/bookstack-content-sync-smoke` 做 push plan 验证
2. 是否本轮只做到 push plan，不做 execute
3. 如果 push plan 成功，是否下一轮再做 execute
4. 是否在 push 迁移后补充包级依赖声明

## 结论

- 状态：待确认
- 当前推荐：迁移 push 后先只验证 push plan
