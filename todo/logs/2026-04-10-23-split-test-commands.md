# 执行日志：2026-04-10 / 测试命令拆分

## 本次目标

- 为单元测试和集成测试增加单独的 Composer 命令
- 让日常测试执行更方便

## 实际执行

- 更新 `composer.json`，新增：
  - `scripts.test-unit = phpunit tests/Unit`
  - `scripts.test-integration = phpunit tests/Integration`
- 更新 `README.md`，补充拆分后的测试运行方式

## 结果

- 现在支持：
  - `composer test`
  - `composer test-unit`
  - `composer test-integration`

## 下一步

- 如需要，可继续增加更细粒度的测试脚本，例如 pull / push 分组
