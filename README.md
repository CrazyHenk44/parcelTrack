# ParcelTrack

A simple, self-hosted application to track parcels from PostNL, DHL, and other carriers via Ship24, with Apprise notifications for status updates.

![ParcelTrack Screenshot](example.png)

## ✨ Features

*   **Multi-Shipper Support**: Track packages from DHL, PostNL, and any carrier supported by Ship24.
*   **Modern Web UI**: A clean, responsive single-page application to view and manage your packages.
*   **Automated Status Updates**: A cron job periodically fetches the latest tracking status for all active packages.
*   **Flexible Notifications**: Receive notifications via a wide range of services, powered by [Apprise](https://github.com/caronc/apprise).
*   **Automatic Archiving**: Delivered packages are automatically marked as `inactive`.
*   **Custom Naming**: Assign custom, friendly names to your packages for easy identification.
*   **Light & Dark Mode**: The UI respects your system's color scheme preference.

## 🚀 Getting Started

You can run ParcelTrack using Docker.

### Docker Setup (Recommended)

To get ParcelTrack up and running quickly using the pre-built Docker image, follow these steps. For local development or if you need to build the Docker image yourself, please refer to the [Local Development Setup](developer/CONTRIBUTING.md#local-development-setup) section in `developer/CONTRIBUTING.md`.

1.  **Download essential files:**
    Download `docker-compose.yml` and `.env.example` from the GitHub repository:
    *   [`docker-compose.yml`](https://github.com/CrazyHenk44/parcelTrack/blob/master/docker-compose.yml)
    *   [`.env.example`](https://github.com/CrazyHenk44/parcelTrack/blob/master/.env.example)
    Place these files in a new directory on your system.

2.  **Pull the latest pre-built Docker image:**
    ```bash
    docker pull ghcr.io/crazyhenk44/parceltrack:master
    ```

3.  **Create an environment file:**
    Rename `.env.example` to `.env` and customize it with your settings. To use Ship24, you must add your API key.

4.  **Run the containers:**
    ```bash
    docker-compose up -d
    ```

### ⚙️ Configuration

The application is configured via environment variables. Create a `.env` file in the project root or set these variables in your environment.

| Variable          | Description                                                              | Example                               |
|-------------------|--------------------------------------------------------------------------|---------------------------------------|
| `PARCELTRACK_URL` | The public URL of your ParcelTrack instance for links in notifications.         | `http://parcels.example.com`          |
| `DEFAULT_COUNTRY`   | The country that packages most likely go to, 2 letters                 | `NL`                     |
| `LOG_LEVEL`       | The minimum log level to output (`DEBUG`, `INFO`, `ERROR`).              | `INFO`                                |
| `SHIP24_API_KEY`  | (Optional) Your API key for Ship24. If provided, enables Ship24 tracking.| `apik_...`                            |
| `APPRISE_URL`    | Space-separated list of [Apprise URLs](https://github.com/caronc/apprise#supported-notifications) for notifications. | `discord://webhook_id/webhook_token` |

## 🔄 Usage

The web interface will be available at `http://localhost:8080` (or the port you configured in your `docker-compose.yml` file).

### Apprise Notifications

ParcelTrack leverages [Apprise](https://github.com/caronc/apprise) for flexible notification delivery. Apprise supports a vast array of notification services, allowing you to receive updates on your preferred platform.

To configure Apprise, set the `APPRISE_URL` environment variable in your `.env` file. This variable should contain a space-separated list of Apprise connection URLs.

**Examples:**

*   **Discord:** `discord://webhook_id/webhook_token`
*   **Telegram:** `tgram://bottoken/chatid`
*   **Slack:** `slack://tokenA/tokenB/tokenC`
*   **Email (SMTP):** `mailto://smarthost.address.lan?from=shipper@test.nl&to=shipper@test.nl`

For a comprehensive list of supported services and their configuration formats, please refer to the [official Apprise documentation](https://github.com/caronc/apprise#supported-notifications).

You can also specify Apprise URLs per package in the "Add Package" wizard. If left empty for a specific package, the global `APPRISE_URL` from your `.env` file will be used.


### Automated Tracking

The Docker setup includes a service that automatically checks for package updates every 5 minutes. If a status changes, it will send a notification to your configured Apprise services. Delivered packages will be automatically moved to an inactive state.

## 📄 License

This project is open-source and licensed under the GNU General Public License v3.0.
