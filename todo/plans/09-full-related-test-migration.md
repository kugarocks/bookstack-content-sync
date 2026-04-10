# 方案：全量相关测试迁移

## 背景

前一阶段已经完成第一批测试迁移，并验证了当前包仓库中的一部分单元测试与轻量集成测试可以运行。

现在策略发生调整：

- 目标不再是“只迁一部分高 ROI 测试”
- 而是迁移 `sync` 分支上所有与当前扩展包代码直接相关的 `ContentSync` 测试

这意味着后续工作应尽量覆盖：

- `tests/Unit/ContentSync/**`
- `tests/Integration/ContentSync/**`

前提是这些测试直接面向当前包已迁移的 `ContentSync` 相关实现。

## 目标

- 迁移所有与当前包代码直接相关的 `ContentSync` 测试
- 让这些测试尽可能在包仓库中独立运行
- 记录哪些测试可以直接迁移，哪些需要适配，哪些仍受宿主耦合限制

## 范围定义

### 纳入迁移范围

- `tests/Unit/ContentSync/Pull/*`
- `tests/Unit/ContentSync/Push/*`
- `tests/Integration/ContentSync/*`

### 迁移原则

- 默认迁移所有相关测试文件
- 不要求 1:1 原样保持旧结构，只要测试意图与覆盖范围保留
- 对 namespace、fixture 路径、辅助类、断言行为进行必要适配
- 如果遇到无法脱离宿主的测试，必须记录原因，而不是静默跳过

## 推荐实施顺序

### Phase 9.1：补齐剩余单元测试

优先补完当前还未迁入的单元测试，包括但不限于：

- `BookStackApiClientTest`
- `BookStackApiRemoteTreeReaderTest`
- `ContentHashBuilderTest`
- `PullResultWriterTest`
- `SyncConfigEnvCredentialResolverTest`
- `LocalContentScannerTest`
- `PushProjectStateLoaderTest`
- 其他尚未迁移的 `Push` / `Pull` 单元测试

### Phase 9.2：补齐剩余集成测试

继续迁移：

- `PullContentRunnerIntegrationTest`
- `PullContentCommandIntegrationTest`
- `PushContentRunnerIntegrationTest`
- `PushContentCommandIntegrationTest`
- `ContentSyncRoundTripIntegrationTest`
- 其他剩余 `ContentSync` 集成测试

### Phase 9.3：整理适配清单

在迁移后，明确分类：

- 已可直接运行
- 已迁移但需要额外 fixture / 配置
- 当前仍强依赖宿主，不适合包内独立运行

## 预期结果

- 包仓库拥有完整的 `ContentSync` 相关测试集
- 至少可以清楚区分哪些测试已可运行、哪些仍待适配
- 后续发布前对回归风险的掌控能力明显提高

## 风险点

### 1. 集成测试对宿主依赖更强

剩余的 integration tests 可能依赖：

- BookStack 内部服务
- 真实或模拟 HTTP 客户端能力
- 文件系统 fixture
- 复杂环境变量与测试目录准备

### 2. 测试迁移不一定等于测试立即可运行

“迁移全部相关测试”不保证每个测试无需修改就能通过。
需要接受部分测试会进入“已迁移但待适配”状态。

### 3. 维护成本上升

全量迁移会带来更高维护成本，但这是当前明确接受的方向。

## 推荐方案

推荐直接按“全量迁移 + 分类收敛”的方式推进。

也就是说：

1. 先把所有相关测试迁入仓库
2. 再跑测试
3. 再分辨哪些需要适配与修复

## 待确认点

1. 是否接受部分测试先迁入但暂时不通过
2. 是否接受为使测试可运行而新增更多测试辅助代码或 fixture
3. 是否允许在迁移过程中分多轮提交，而不是追求一次性全绿

## 结论

- 状态：待确认
- 当前推荐：迁移全部相关测试，并在迁移后做分类适配
