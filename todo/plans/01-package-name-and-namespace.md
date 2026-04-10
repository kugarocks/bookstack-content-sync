# 方案：包名与命名空间

## 背景

当前项目目标是将 BookStack 中已有的 content sync 能力整理为一个可外部安装的 Composer 扩展包。

在进入 `composer.json`、`src/` 骨架和代码迁移之前，需要先确定以下基础标识：

- Composer package name
- PHP namespace
- 仓库目录名与对外名称之间的关系
- 后续文档和安装说明中的统一叫法

这些内容一旦确定，后续所有代码路径、类名、README、发布说明都会依赖它们，因此应先确认。

## 目标

- 选出一个适合长期维护的 Composer 包名
- 选出一个清晰、稳定、不与宿主冲突的 PHP 命名空间
- 明确仓库名、包名、命名空间之间的映射关系
- 为下一步编写 `composer.json` 和 `src/` 骨架提供依据

## 方案选项

### 方案 A

- Composer 包名：`kugarocks/bookstack-content-sync`
- PHP 命名空间：`Kugarocks\\BookStackContentSync`
- 仓库目录名：`bookstack-content-sync`
- 对外名称：BookStack Content Sync

优点：

- 与当前仓库名一致，直观
- `content-sync` 能准确表达功能范围
- 命名空间稳定，和 `BookStack\\...` 宿主命名空间明显区分
- 后续 path repository、本地联调、发布时不容易混淆

缺点：

- 名称相对长一点
- 如果未来功能扩大到“内容同步之外”，名字会稍显窄

### 方案 B

- Composer 包名：`kugarocks/bookstack-sync`
- PHP 命名空间：`Kugarocks\\BookStackSync`
- 仓库目录名：`bookstack-content-sync` 或后续改为 `bookstack-sync`
- 对外名称：BookStack Sync

优点：

- 更短
- 后续如果加入更多同步能力，扩展空间更大

缺点：

- `sync` 语义偏宽，容易与其他同步方向混淆
- 如果当前核心能力主要针对 content project，表达不如 A 准确
- 未来可能与别的 sync 工具命名撞得更近

### 方案 C

- Composer 包名：`kugarocks/bookstack-content-sync-extension`
- PHP 命名空间：`Kugarocks\\BookStackContentSyncExtension`
- 仓库目录名：`bookstack-content-sync`
- 对外名称：BookStack Content Sync Extension

优点：

- 明确表达“这是扩展包”
- 与宿主主项目区分很强

缺点：

- 太长
- 日常使用、代码命名、文档展示都偏笨重
- `extension` 对 Composer 用户来说是多余信息

## 推荐方案

推荐采用方案 A。

推荐理由：

- 当前目标非常明确，就是把 content sync 做成外部 Composer 扩展包
- `bookstack-content-sync` 与当前 repo 目录一致，迁移成本最低
- `Kugarocks\\BookStackContentSync` 能和宿主 `BookStack\\...` 清晰分层
- 先把名字定得准确，比定得过宽更重要

## 命名约束建议

### Composer 包名

建议固定为：

- `kugarocks/bookstack-content-sync`

### 命名空间

建议固定为：

- `Kugarocks\\BookStackContentSync`

### 类命名规则

建议采用：

- `Kugarocks\\BookStackContentSync\\ContentSync\\Pull\\...`
- `Kugarocks\\BookStackContentSync\\ContentSync\\Push\\...`
- `Kugarocks\\BookStackContentSync\\Console\\Commands\\...`
- `Kugarocks\\BookStackContentSync\\Providers\\...`

说明：

- 先保留 `ContentSync` 这一层，方便从现有代码迁移
- 不与宿主 `BookStack\\ContentSync\\...` 直接重名
- 后续如果需要瘦身，可以在第二阶段再整理目录层级

## 待确认点

1. 是否接受包名使用 `content-sync` 而不是更宽泛的 `sync`
2. 是否接受命名空间使用 `Kugarocks` 前缀
3. 是否保留 `ContentSync` 作为 namespace 中间层，便于平滑迁移
4. 仓库目录是否也维持 `bookstack-content-sync` 不再调整

## 结论

- 状态：待确认
- 当前推荐：方案 A
