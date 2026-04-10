# 执行日志：2026-04-10 / 最小 Pull 实跑执行

## 本次目标

- 创建一个最小 sync 项目目录
- 在 BookStack 宿主中执行一次真实 `bookstack:pull-content`
- 确认 pull 是否真的能从外部包跑通到文件输出

## 实际执行

- 创建测试目录：`/tmp/bookstack-content-sync-smoke`
- 写入最小 `sync.json`
- 使用宿主本地环境变量：
  - `BOOKSTACK_API_TOKEN_ID`
  - `BOOKSTACK_API_TOKEN_SECRET`
- 在宿主项目中执行：
  - `php artisan bookstack:pull-content /tmp/bookstack-content-sync-smoke`
- 检查输出目录与 `snapshot.json`

## 结果

- pull 命令执行成功
- 终端输出显示完整 pull 阶段日志
- 输出统计为：
  - `EXPORTED FILES = 26`
  - `SNAPSHOT NODES = 26`
- 测试目录中成功生成：
  - `sync.json`
  - `snapshot.json`
  - `content/` 目录及导出文件

## 问题与判断

- 当前外部包不仅完成了命令注册，还已经真实跑通了 pull 链路
- 说明 shared / pull 迁移结果在当前宿主环境下是可工作的
- 下一阶段的主要工作重心可以转向 push 迁移，而不是继续修 pull 安装链路

## 下一步

- 开始规划并迁移 push plan / execute 链路
- 视需要补充扩展包 `composer.json` 的依赖声明
- 保留 `/tmp/bookstack-content-sync-smoke` 作为后续 push 验证候选目录
