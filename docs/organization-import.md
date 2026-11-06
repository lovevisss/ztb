# 学校组织数据导入

系统管理员进入“学校组织同步”，上传或粘贴 UTF-8 JSON 文件。导入在一个数据库事务中完成；部门层级错误、岗位引用缺失、重复账号或邮箱冲突会拒绝整个文件。导入记录显示操作者、数量和停用人数。

```json
{
  "complete_snapshot": false,
  "departments": [
    {"code": "SCHOOL", "name": "学校", "parent_code": null},
    {"code": "IT", "name": "信息中心", "parent_code": "SCHOOL", "active": true}
  ],
  "positions": [{"code": "HEAD", "name": "部门负责人"}],
  "people": [
    {
      "account": "001",
      "name": "张老师",
      "email": "zhang@example.edu.cn",
      "department_code": "IT",
      "position_code": "HEAD",
      "active": true
    }
  ]
}
```

每次导入须包含被引用部门及其完整父级链、被引用岗位。`account` 是 CAS 登录账号，必须与学校 CAS 返回值一致。邮箱可省略；系统会为新人员生成不可投递的占位邮箱。导入不会按同名或同邮箱自动合并账号。

`complete_snapshot=false` 只更新文件中的人员。确认文件含全部在职人员后才使用 `true`，此时此前由组织导入的缺席人员会停用；手工账号不会因此停用。明确标记 `active=false` 的人员也会停用。停用账号不能登录、被新流程选为审批人或被安排为执行人。已生成的待办需要管理员转派。

岗位与采购角色的对应关系在页面上单独维护，调整后立即更新该岗位的人员角色。学校组织接口协议到位后可在 `SchoolOrganizationSync` 前接入定时拉取或推送适配器，沿用同一事务与校验规则。
