# 执行日志：2026-04-10 / 第一批测试迁移

## 本次目标

- 将第一批纯单元测试迁移到扩展包仓库
- 同时迁移一批轻量集成测试
- 建立扩展包自身的 PHPUnit 运行基础设施

## 实际执行

- 更新测试迁移策略，明确轻量集成测试也纳入迁移范围
- 从 `sync` 分支迁移第一批单元测试到 `tests/Unit/ContentSync/*`
- 迁移 `PushPlanRunnerIntegrationTest` 到 `tests/Integration/ContentSync/`
- 迁移 `PullNodeFactory` 与 `PushNodeFactory`
- 更新 `composer.json`，增加：
  - `require-dev.phpunit/phpunit`
  - `autoload-dev.Tests\`
- 新建 `phpunit.xml`
- 生成 `composer.lock`
- 新建 `.gitignore`，忽略 `vendor/` 与 PHPUnit cache
- 根据当前 push 实现行为，调整一个测试断言以匹配 `sync_membership` 动作
- 执行：
  - `vendor/bin/phpunit tests/Unit/ContentSync/Pull tests/Unit/ContentSync/Push tests/Integration/ContentSync/PushPlanRunnerIntegrationTest.php`

## 结果

- 第一批测试迁移完成
- PHPUnit 在当前包仓库中可运行
- 当前通过：
  - `50 tests`
  - `138 assertions`

## 问题与判断

- `PushPlanBuilderTest` 中有一个断言需要根据当前实现补充 `sync_membership` 预期
- 这说明测试迁移时应以“保护当前包行为”为目标，而不是机械复制旧断言
- 当前已具备继续迁移更多单元测试与轻量集成测试的基础

## 下一步

- 继续迁移第二批单元测试
- 挑选下一批轻量集成测试
- 评估是否需要把宿主验证步骤脚本化
