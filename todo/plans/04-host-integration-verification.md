# 方案：宿主集成验证

## 背景

当前扩展包已经完成第一轮真实 pull 迁移，但还没有在 BookStack 宿主项目中实际安装验证。

在继续迁移 push 之前，先验证宿主集成有几个价值：

- 确认 package discovery 是否生效
- 确认 provider 和命令能否在宿主中正确注册
- 确认当前依赖关系是否足以支撑 pull 链路运行
- 提前暴露 composer、autoload、宿主依赖、命令冲突等集成问题

## 目标

- 在本地 BookStack 宿主项目中通过 path repository 安装本包
- 验证 provider 自动发现与命令注册
- 验证 `bookstack:pull-content` 是否可见并可执行到真实实现
- 记录需要补充的依赖、配置或兼容性问题

## 推荐验证步骤

### 步骤 1：接入 path repository

在宿主项目 `composer.json` 中增加本地 path repository，指向：

- `/Users/kuga/github/kugarocks/bookstack-content-sync`

然后在宿主项目中 require：

- `kugarocks/bookstack-content-sync:*`

目标：

- 让宿主优先从本地目录安装扩展包

### 步骤 2：执行 Composer 更新

对宿主项目执行针对该包的 Composer 更新或安装。

重点关注：

- autoload 是否成功生成
- Laravel package discovery 是否报错
- 是否缺少 package 级依赖声明

### 步骤 3：验证 Artisan 命令注册

在宿主项目中检查：

- `php artisan list | grep bookstack:`
- 或直接执行 `php artisan bookstack:pull-content --help`

目标：

- 确认命令签名已由扩展包接管
- 确认 provider 已被宿主识别

### 步骤 4：验证 pull 链路最小运行

选择一个测试目录，执行：

- `php artisan bookstack:pull-content {projectPath}`

目标：

- 确认命令进入真实 runner
- 如果失败，记录失败点属于：
  - Composer 依赖问题
  - 容器绑定问题
  - 宿主内部服务依赖问题
  - API / 配置问题

## 风险点

### 1. 包依赖声明不足

当前扩展包 `composer.json` 只声明了 PHP 版本，尚未显式声明：

- `illuminate/*`
- `guzzlehttp/psr7`
- 或其他 pull 链路直接使用的类来源

如果这些依赖只依靠宿主间接提供，集成验证时可能仍能通过，但文档和包定义可能不够完整。

### 2. 命令名冲突

宿主项目如果仍保留原始 `bookstack:pull-content` 命令，实现优先级可能需要确认。

### 3. 包自动发现不生效

如果 package discovery 没有正确加载，需要确认：

- `extra.laravel.providers` 是否生效
- 宿主是否缓存了旧的服务发现结果

## 执行策略建议

推荐分成两小步：

### 方案 A

先只做“安装与命令注册验证”，不强求第一次就成功执行 pull。

优点：

- 风险更低
- 更容易定位是安装问题还是运行问题

### 方案 B

一次性做“安装 + 命令注册 + pull 运行验证”。

优点：

- 更快得到真实结果

缺点：

- 如果失败，问题来源更杂

## 推荐方案

推荐采用方案 A 起步，但在命令注册验证顺利时，继续推进到最小 pull 运行验证。

换句话说，执行顺序建议是：

1. 安装包
2. 验证命令注册
3. 若前两步顺利，再执行一次最小 pull 验证

## 待确认点

1. 是否允许修改宿主项目 `composer.json` 以增加 path repository
2. 是否直接在当前 BookStack 工作区做本地安装验证
3. 如果出现宿主已有命令冲突，是先记录问题还是立即改命令名策略
4. 集成验证后是否需要立刻补齐包的 `require` 字段

## 结论

- 状态：待确认
- 当前推荐：先按方案 A 验证安装与命令注册，再继续最小 pull 运行验证
