## Description
This WordPress plugin syncs WordPress entities (posts, pages, categories, tags, menus, etc.) with ClickUp tasks.

Additional features:
- A queue system and ClickUp API rate limiter, to avoid hitting the ClickUp API rate limit.
- Connections panel to list all active connections between WP entities and ClickUp Tasks.
- A log system to track all sync operations and errors.

Note: Although this is a fully functional plugin, it's meant to be published as proof of concept, allowing others to
adapt it to their needs.

## Installation
- Copy "wp-clickup-sync" folder to your WordPress plugins folder and activate it.
- Add your ClickUp API key in the plugin settings page.
- Grab the List ID of the ClickUp list you want to sync with. It can be found in URL as 
  app.clickup.com/1234567/v/l/6-[list-id]-1 or app.clickup.com/1234567/v/li/[list-id].
- Add the List ID to `ClickUp Sync` -> `Settings` -> `Connect Posts` or `Connect Terms`, save changes to pull & 
  link the fields.

## Screenshots
![Connections Panel](docs/screenshot-activate.png)
![Connections Panel](docs/screenshot-connect.png)

## License
This is free and unencumbered software released into the public domain. For more information, please refer to [LICENSE.md](LICENSE.md).