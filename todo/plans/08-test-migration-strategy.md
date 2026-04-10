# 方案：测试迁移策略

## 背景

当前扩展包已经完成：

- 核心代码迁移
- 宿主安装验证
- pull 实跑验证
- push plan 验证
- 无变化场景下的 push execute 验证

这说明功能层面已经具备较高可用性，但当前最大的缺口是：

- 包仓库内部缺少系统化自动化测试
- 当前验证结果主要依赖手工执行与日志记录
- 后续如果继续改代码，容易出现回归

因此，需要规划如何从 `BookStack` 的 `sync` 分支中迁移合适的测试到当前外部包仓库。

## 目标

- 明确哪些测试值得迁移
- 明确哪些测试暂时不迁移
- 给出第一批迁移建议
- 为测试迁移建立阶段性实施顺序

## 测试分类策略

### 第一类：优先迁移的纯单元测试

特征：

- 不依赖完整宿主应用启动
- 不依赖数据库
- 不依赖 BookStack 特定测试基类
- 主要验证纯逻辑类行为

适合优先迁移的候选方向：

- `SyncConfigLoaderTest`
- `PullPathBuilderTest`
- `SnapshotBuilderTest`
- `MetaFileBuilderTest`
- `PageFileBuilderTest`
- `ContentHashBuilderTest`
- `LocalFileParserTest`
- `SnapshotMatcherTest`
- `StructureDifferTest`
- `ContentDifferTest`
- `PushPlanBuilderTest`
- `ProjectStructureValidatorTest`
- `PushPlanSummaryTest`

优点：

- 成本最低
- 回归保护价值高
- 最适合直接服务于当前包代码

## 第二类：谨慎迁移的轻量集成测试

特征：

- 验证多个协作类串联起来的结果
- 可能需要文件系统 fixture
- 但不应强依赖完整宿主数据库或完整宿主应用生命周期

适合后续筛选迁移的候选方向：

- pull runner 最小成功路径
- push plan runner 最小成功路径
- local project state 加载与 snapshot 匹配链路

优点：

- 能保护核心链路组合行为

缺点：

- 改造成本高于纯单元测试
- 需要重新整理 fixture 与测试目录结构

## 第三类：暂不迁移的重型宿主测试

不建议当前迁移：

- 依赖 BookStack 宿主完整应用容器的大型集成测试
- 依赖宿主数据库、真实 API token、复杂 seed 数据的测试
- 偏 E2E / 宿主环境性质的测试

原因：

- 这些测试更适合作为宿主验证或脚本化 smoke test 保留
- 如果直接迁入包仓库，会显著增加维护成本
- 容易让包测试体系过度依赖宿主实现细节

## 推荐迁移顺序

### Phase 8.1：先迁纯单元测试

建议先挑一批最有代表性的测试迁移，例如：

- `tests/Unit/ContentSync/Pull/SyncConfigLoaderTest.php`
- `tests/Unit/ContentSync/Pull/PullPathBuilderTest.php`
- `tests/Unit/ContentSync/Pull/SnapshotBuilderTest.php`
- `tests/Unit/ContentSync/Pull/MetaFileBuilderTest.php`
- `tests/Unit/ContentSync/Pull/PageFileBuilderTest.php`
- `tests/Unit/ContentSync/Push/LocalFileParserTest.php`
- `tests/Unit/ContentSync/Push/SnapshotMatcherTest.php`
- `tests/Unit/ContentSync/Push/StructureDifferTest.php`
- `tests/Unit/ContentSync/Push/ContentDifferTest.php`
- `tests/Unit/ContentSync/Push/ProjectStructureValidatorTest.php`

目标：

- 先把最关键的纯逻辑组件纳入自动化保护

### Phase 8.2：补充 push / pull plan 相关单元测试

在第一批成功后，再补：

- `PushPlanBuilderTest`
- `PushPlanSummaryTest`
- `PullResultBuilderTest`
- `SnapshotJsonBuilderTest`
- 其他辅助工厂和 fixture

### Phase 8.3：再考虑轻量集成测试

在单元测试体系稳定后，再决定是否迁：

- runner 级最小链路集成测试
- 文件系统 fixture 驱动的 smoke-style 测试

## 推荐方案

推荐先执行 Phase 8.1。

原因：

- 当前 ROI 最高
- 可以快速把已经验证过的核心逻辑固化为包内回归保护
- 不会过早把测试体系变成宿主耦合型

## 迁移方式建议

- 不要求测试文件 1:1 原样复制
- 更推荐“迁测试价值”，即：
  - 保留测试意图
  - 重写 namespace
  - 调整 fixture 路径
  - 去掉宿主特有依赖

## 待确认点

1. 是否同意优先迁纯单元测试而不是先迁集成测试
2. 第一批是否按上述 10 个左右的测试文件开始
3. 是否允许对原测试进行适度重写，而不是强求原样迁移
4. 宿主级验证是否继续保留为手工 / 脚本验证，而不立即并入 PHPUnit

## 结论

- 状态：待确认
- 当前推荐：先迁纯单元测试，后补轻量集成测试，暂不迁重型宿主测试
