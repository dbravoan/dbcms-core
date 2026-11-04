# dbCMS core

Core provides the web installer, identity/permissions, languages and markets,
media, pages, menus, publication, sections, theme resolution, technical SEO and
privacy. Laravel project code stays under `App`; core classes use `DbCms\Core`.

Open `/install` on a fresh deployment. Supply actual database/controller details
and create the administrator. The installer starts an empty site. Configure its
languages and markets before creating content; translating an entity does not
publish it or assign it to every market.

In the backoffice, create a Page, select its language/markets, and choose sections
or a registered Blade template. Save the draft, then publish through the applicable
review flow. Verify the anonymous public URL in the intended language and market.
A disabled block owner hides execution but preserves stored instances.

Use the media library and menu manager under their normal permissions. The legal
footer and consent UI use the same configured company/processing data; review
them whenever integrations change. Full custom layouts must include the shared
privacy UI.

See [architecture](https://github.com/dbravoan/dbcms/blob/main/specs/CORE_AND_EXTENSIONS.md)
and [extensions/Blade](https://github.com/dbravoan/dbcms/blob/main/docs/EXTENSIONS.md) for the integration contract.
