# 进度追踪

## 当前状态

- 项目状态：功能迁移与测试已完成，进入发布前整理阶段
- 当前阶段：Phase 10 - Release and polish preparation
- 最后更新时间：2026-04-10

## 里程碑进度

| 里程碑 | 状态 | 说明 |
| --- | --- | --- |
| 方案冻结 | 已完成 | 已建立并持续补充方案文档 |
| 外部包骨架搭建 | 已完成 | 最小 Composer 骨架、provider 与命令占位已建立 |
| 同步核心代码迁移 | 已完成 | Shared、Pull、Push 已全部迁移到扩展包 |
| Provider 与命令接入 | 已完成 | Pull 与 Push 命令均已由扩展包真实实现接管 |
| 宿主安装验证 | 已完成 | 已完成 path repository 安装、命令接管与最小 pull 实跑 |
| 文档与发布准备 | 进行中 | README、安装说明、发布清单、首发版本策略、首发说明草案、发布命令顺序与宿主真实写入验证均已补充，仍待决定首个正式 tag |

## 已完成

### 2026-04-10

- 新建独立仓库 `bookstack-content-sync`
- 初始化 Git 仓库
- 建立 `todo/` 目录规范
- 补充项目总览、实施方案、进度追踪文档
- 新增 `todo/plans/` 目录，用于先确认后执行
- 新增首份待确认方案文档：`todo/plans/01-package-name-and-namespace.md`
- 新增第二份待确认方案文档：`todo/plans/02-composer-package-skeleton.md`
- 新增第三份待确认方案文档：`todo/plans/03-code-migration-boundary.md`
- 新增第四份待确认方案文档：`todo/plans/04-host-integration-verification.md`
- 新增第五份待确认方案文档：`todo/plans/05-minimal-pull-run-verification.md`
- 新增第六份待确认方案文档：`todo/plans/06-push-migration-and-verification.md`
- 新增第七份待确认方案文档：`todo/plans/07-push-execute-verification.md`
- 新增第八份待确认方案文档：`todo/plans/08-test-migration-strategy.md`
- 新增第九份待确认方案文档：`todo/plans/09-full-related-test-migration.md`
- 恢复第一轮真实 pull 代码迁移
- 确定日志按执行轮次记录
- 完成 Push 核心实现迁移
- 完成宿主 path repository 安装与命令接管验证
- 完成最小 pull 实跑验证
- 完成 push plan 与 no-change push execute 验证
- 完成所有直接相关的单元测试与集成测试迁移
- 增加 `composer test`、`composer test-unit`、`composer test-integration`、`composer test-pull`、`composer test-push`
- 完成 PHP 命名空间从 `KugaRocks` 到 `Kugarocks` 的统一
- 补充发布清单与首发版本策略文档
- 完成宿主侧最小真实写入型 `push --execute` 验证，并在验证后恢复测试内容
- 新增 `v0.1.0` 首发说明草案，并通过 `.gitattributes` 排除发布产物中的 `todo/`
- 补充 `v0.1.0` 的实际发布命令顺序文档

## 下一步建议

1. 决定是否现在正式打出 `v0.1.0`
2. 基于 `docs/release-notes-v0.1.0.md` 收敛首发 release note
3. 按 `docs/publish-commands-v0.1.0.md` 执行实际 tag 与 push
4. 如有需要，再补宿主侧集成说明或保留验证脚本

## 阻塞项

- 暂无硬阻塞
- 发布前仍需进一步收敛兼容性承诺与版本策略
