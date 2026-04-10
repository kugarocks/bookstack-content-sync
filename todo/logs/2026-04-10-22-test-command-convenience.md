# 执行日志：2026-04-10 / 测试命令便捷化

## 本次目标

- 为包仓库增加统一的测试入口命令
- 在 README 中记录推荐测试运行方式

## 实际执行

- 更新 `composer.json`，新增：
  - `scripts.test = phpunit tests`
- 更新 `README.md`，补充测试运行说明

## 结果

- 以后可直接使用：
  - `composer test`
- 仍保留直接使用：
  - `vendor/bin/phpunit tests`

## 下一步

- 如需要，可继续补充更细粒度的测试脚本，例如 unit / integration 分开运行
