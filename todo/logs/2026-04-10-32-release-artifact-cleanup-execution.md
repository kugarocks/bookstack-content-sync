# 执行日志：2026-04-10 / 发布产物清理与首发说明执行

## 本次目标

- 确保 `todo/` 不进入发布产物
- 补 `v0.1.0` 的 release note 草案
- 同步现有发布文档口径

## 实际执行

- 新增 `.gitattributes`，使用 `/todo export-ignore` 排除发布归档中的 `todo/`
- 新增 `docs/release-notes-v0.1.0.md`，作为首发版本说明草案
- 更新 `README.md`、`docs/versioning-and-publishing.md` 与 `docs/release-checklist.md`，补充 release note 与发布产物说明
- 更新 `todo/02-progress.md`，同步当前发布准备状态

## 结果

- 使用 `git archive --worktree-attributes` 检查后，`todo/` 已按预期被排除在发布归档之外
- 首发文档之间的引用关系已对齐
