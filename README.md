# LandmarkUS WordPress 发布代码

本仓库保存用于准备 Hostinger 发布的 Blocksy 子主题与业务插件，当前为本地开发版本的独立代码快照。

## 目录

- `wp-content/themes/landmarkus-pilot-child/` 包含子主题及自有素材。
- `wp-content/plugins/landmarkus-core/` 包含批发价格、批量订货、采购搜索和询盘接线。
- `scripts/validate.py` 检查发布范围、文件校验和和本地文件排除。
- `scripts/package.py` 生成可下载的代码 ZIP。
- `source-snapshot.json` 记录来源版本，打包时生成每个发布文件的校验和。

每次 push 或 Pull Request 后，GitHub Actions 自动检查发布范围、PHP 8.2 与 JavaScript 语法并生成代码包。通过后在 Actions 的 Artifacts 下载 `landmarkus-wordpress-code`。

## 日常更新

修改 CSS、JS、PHP 模板或业务插件后，提交代码并验证，再更新 Hostinger 上对应自有目录。商品、库存、订单、客户、设置和 Gutenberg 正文继续保存在目标 WordPress 的数据库中。

页面中的 `*-content.html` 是样板和块模式来源。更新这些文件不会自动覆盖数据库中已保存的页面。页面修改在目标 WordPress 后台保存，或通过经过核对的页面迁移同步。

首次接入还需处理新版页面、菜单、配置和新增媒体，并确认目标站已安装 Blocksy、WooCommerce、Contact Form 7 和 Flamingo。正式发布使用独立发布与回滚流程。

见 [Hostinger 接入说明](HOSTINGER.md)。
