# ParcelTrack

A simple, self-hosted application to track parcels from PostNL, DHL, and other carriers via Ship24, with email notifications for status updates.

![ParcelTrack Screenshot](example.png)

## ✨ Features

*   **Multi-Shipper Support**: Track packages from DHL, PostNL, and any carrier supported by Ship24.
*   **Modern Web UI**: A clean, responsive single-page application to view and manage your packages.
*   **Automated Status Updates**: A cron job periodically fetches the latest tracking status for all active packages.
*   **Email Notifications**: Receive an HTML email notification when a package's status changes.
*   **Automatic Archiving**: Delivered packages are automatically marked as `inactive`.
*   **Custom Naming**: Assign custom, friendly names to your packages for easy identification.
*   **Light & Dark Mode**: The UI respects your system's color scheme preference.

## 🚀 Getting Started

You can run ParcelTrack using Docker.

### Docker Setup (Recommended)

1.  **Clone the repository:**
    ```bash
    git clone https://github.com/CrazyHenk44/parcel-track.git
    cd parcel-track
    ```

2.  **Create an environment file:**
    Copy the example environment file and customize it with your settings.
    ```bash
    cp .env.example .env
    ```
    Now, edit the `.env` file. To use Ship24, you must add your API key.

3.  **Build and run the containers:**
    ```bash
    docker-compose up --build -d
    ```

### ⚙️ Configuration

The application is configured via environment variables. Create a `.env` file in the project root or set these variables in your environment.

| Variable          | Description                                                              | Example                               |
|-------------------|--------------------------------------------------------------------------|---------------------------------------|
| `PARCELTRACK_URL` | The public URL of your ParcelTrack instance for links in emails.         | `http://parcels.example.com`          |
| `DEFAULT_EMAIL`   | The default email address to send notifications to.                      | `you@example.com`                     |
| `LOG_LEVEL`       | The minimum log level to output (`DEBUG`, `INFO`, `ERROR`).              | `INFO`                                |
| `SHIP24_API_KEY`  | (Optional) Your API key for Ship24. If provided, enables Ship24 tracking.| `apik_...`                            |
| `SMTP_HOST`       | Your SMTP server hostname.                                               | `smtp.mailgun.org`                    |
| `SMTP_PORT`       | Your SMTP server port.                                                   | `587`                                 |
| `SMTP_FROM`       | The "From" address for notification emails.                              | `noreply@example.com`                 |
| `SMTP_USER`       | (Optional) The username for SMTP authentication.                         | `postmaster@example.com`              |
| `SMTP_PASS`       | (Optional) The password for SMTP authentication.                         | `super-secret-password`               |

## 🔄 Usage

The web interface will be available at `http://localhost:8080` (or the port you configured in your `.env` file).

### Automated Tracking

The Docker setup includes a cron service that automatically checks for package updates every 5 minutes. If a status changes, it will send a notification to the configured email address. Delivered packages will be automatically moved to an inactive state.

## 📄 License

This project is open-source and licensed under the GNU General Public License v3.0.