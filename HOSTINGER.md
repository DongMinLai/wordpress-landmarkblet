# Hostinger 接入与发布范围

## 需要提供的接入信息

Hostinger 套餐类型、域名、WordPress 安装路径及发布分支。若采用 GitHub Actions 同步，还需核对 SSH 主机、端口、用户和部署公钥授权。私钥保存在 GitHub Actions Secrets，服务器继续使用现有 wp-config.php。

账户若提供原生 Git 功能，可在网站 Dashboard → Advanced → Git 连接 GitHub，选择仓库、分支和目标目录。Hostinger 文档中的默认目标是 public_html，连接或切换仓库可能覆盖目标目录。本站应将部署范围落实到自有主题和插件，先验证预览站。

[Hostinger 官方 Git 部署说明](https://www.hostinger.com/support/1583302-how-to-deploy-a-git-repository-in-hostinger/)

## 哪些内容怎样更新

| 改动 | 更新方式 |
| --- | --- |
| CSS、JS、PHP 模板和业务插件 | GitHub 验证后发布对应代码目录。 |
| Gutenberg 文案、布局、商品短码 | 在目标后台保存，或按页面同步。 |
| 新产品图片、首图和媒体附件 | 同步对应文件及必要媒体记录，核对路径和附件。 |
| 商品价格、库存、订单和客户 | 使用目标站 WooCommerce 数据库。 |
| 新功能的表结构或设置 | 独立、可核对的版本迁移。 |

日常代码更新无需携带数据库转储，也无需把数据库密码放入 GitHub。

## 首次迁移

代码就位后，核对依赖、主题激活、页面 slug、商品 SKU 与分类、图片、菜单、表单接线，以及登录看价、配送和付款。Gutenberg 页面从 Local 到 Hostinger 需要单独迁移。页面迁移应对应目标数据，保留线上已有订单和客户。

当前仓库完成代码快照和验证流程准备。Hostinger 连接和预览站配置在目标账户信息确认后接入。


Golf 专题的代码与当前使用的四张自有素材一并收录于子主题的 golf-collection 目录。该专题此前由 Local 专用路由接入，目标站首次安装需另行接好页面和模板路由。
