# 执行日志：2026-04-10 / 命名空间规范化

## 本次目标

- 将 PHP 命名空间前缀从 `KugaRocks` 统一调整为 `Kugarocks`
- 保持 Composer 包名不变，继续使用小写的 `kugarocks/bookstack-content-sync`

## 实际执行

- 批量更新 `src/` 下的 namespace 与 import
- 批量更新 `tests/` 中对扩展包命名空间的引用
- 更新 `composer.json` 中的 PSR-4 autoload 与 provider 类名
- 同步修正 `todo/` 中涉及命名空间示例的文档记录

## 结果

- `composer dump-autoload` 已通过
- `composer test` 已通过，结果为 `93 tests, 364 assertions`
