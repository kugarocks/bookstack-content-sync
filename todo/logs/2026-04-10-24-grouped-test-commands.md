# 执行日志：2026-04-10 / 分组测试命令

## 本次目标

- 增加按 pull / push 维度分组的测试命令
- 让日常开发更容易针对具体功能域运行测试

## 实际执行

- 更新 `composer.json`，新增：
  - `scripts.test-pull`
  - `scripts.test-push`
- 更新 `README.md`，补充分组测试命令说明

## 结果

- 现在支持：
  - `composer test`
  - `composer test-unit`
  - `composer test-integration`
  - `composer test-pull`
  - `composer test-push`

## 下一步

- 如需要，可继续补充 CI 或发布前检查脚本
