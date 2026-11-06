# 东方学院 CAS 对接

项目使用 `lovevisss/laravel-zufedfc-cas` v0.1.0 处理 CAS 协议。应用自己维护人员映射、在职状态和业务权限。包提供票据校验、TLS 校验、标准 SLO 与学校 JSONP SLO；应用路由把包控制器放在 Laravel `web` 会话中间件下。

## 学校平台需登记的地址

| 用途               | HTTPS 地址                                     |
| ------------------ | ---------------------------------------------- |
| 应用首页           | `https://ztb.zufedfc.edu.cn/`                  |
| Service / 匹配 URL | `https://ztb.zufedfc.edu.cn/auth/cas/callback` |
| 登录回调           | `https://ztb.zufedfc.edu.cn/auth/cas/callback` |
| 单点注销 SLO       | `https://ztb.zufedfc.edu.cn/auth/cas/slo`      |
| 注销后返回         | `https://ztb.zufedfc.edu.cn/`                  |

`CAS_SERVICE_URL` 必须与登记的回调地址完全一致，包含协议、域名、端口（如有）和路径。当前地址来自项目提供的正式域名；上线前请确认反向代理最终对外也是 HTTPS，且平台已登记这些地址。不要把本地 Herd 或 `artisan serve` 地址登记到学校平台。

## 环境和账号

- `.env.example` 已列出 `CAS_BASE_URL`、`CAS_SERVICE_URL`、`CAS_POST_LOGOUT_URL`、`CAS_ACCOUNT_ATTRIBUTE=user`、`CAS_VERIFY_TLS=true` 等配置；本地 `.env` 已写入正式回调与退出返回地址。
- 解析器只按 CAS `user` 与 `users.school_account` 做精确匹配。人员必须在职且关联部门。缺少映射、停用或无部门时拒绝登录，页面为 `/access-denied`。
- 系统管理员可在 `/admin/school-identities` 关联已有用户并维护在职状态；变更保留审计记录。当前尚未接入学校组织和人员同步接口，所以正式启用前需先导入并核对账号映射。
- 登录成功或被拒绝时记录账号 SHA-256 摘要和结果；不保存原始 CAS Ticket 或完整认证响应。
- CAS 登录会重新生成应用会话 ID。项目内使用 `/auth/cas/logout` 退出并跳转学校 CAS；SLO 使用 `/auth/cas/slo`，此端点免 CSRF，支持标准 `logoutRequest` 和学校 JSONP 回调。
- 单点注销需要各应用节点共享会话存储与缓存。生产环境应使用数据库或其他共享会话存储，并配置共享缓存；默认不启用在线状态轮询。
- 目前保留原有 Fortify 本地登录作为过渡。学校 CAS 实际登录、人员同步与门户接口联调完成后，再切换为统一认证入口并停用本地自注册和密码登录。

## 已验证与待联调

自动化用例覆盖精确 Service、返回地址拦截、CAS 票据校验、无权限账号、停用会话、TLS/网络失败处理、会话重建、注销和 SLO。测试使用模拟 CAS 响应，没有使用真实票据。学校 CAS 实际登录与退出、跨节点 SLO、组织同步和门户待办仍需在学校环境联调验证。
