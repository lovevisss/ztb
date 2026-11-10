# 正式环境手机号补录

本操作仅按 `t_cx_zzqxryxx.xgh = users.school_account` 将非空的 `yddh` 更新到已有账号的 `users.mobile_phone`。不会创建或停用账号，也不会修改部门、岗位、姓名、申请草稿或已提交申请。

## 首次执行

1. 按现有发布流程部署代码，并备份正式环境数据库。确认正式环境的 `SCHOOL_DB_HOST`、`SCHOOL_DB_PORT`、`SCHOOL_DB_DATABASE`、`SCHOOL_DB_USERNAME`、`SCHOOL_DB_PASSWORD` 已配置，且源库账号可读取 `t_cx_zzqxryxx`。
2. 在正式项目目录执行 `php artisan migrate:status`，确认 `2026_11_08_000000_add_mobile_phone_to_users` 已运行；如未运行，按发布流程执行 `php artisan migrate --force`，再复查迁移状态。
3. 执行 `php artisan school:sync-phones --dry-run`。核对来源有效号码、匹配账号、待更新、未匹配和跳过数量；预览不会写入用户表。来源为空、没有有效号码或工号重复时命令会失败，不应继续执行。
4. 确认预览数量符合预期后，执行 `php artisan school:sync-phones`。再次执行 `php artisan school:sync-phones --dry-run`，正常情况下“待更新”为 0。用有源手机号的账号打开**新申请**，确认经办人电话自动带出。

命令只更新手机号与当前值不同的匹配账号。源号码为空时保留本地号码；未匹配的源人员不会被创建。已保存草稿仍显示草稿中保存的电话。

## 后续与现有定时任务

需要再次补录时可重复执行该命令。本次不要运行 `school:sync-organization` 来补手机号，因为它还会同步部门、岗位和账号状态。

仓库目前仍在 `routes/console.php` 中安排每天北京时间 02:00 的**完整组织同步**。如果正式服务器启用了 Laravel 调度器，该现有任务仍会按原规则运行；手机号专用命令不会改变它。正式环境是否启用该完整同步，应按现有组织数据运维策略确认。
