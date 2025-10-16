# Contributing to ParcelTrack

First off, thank you for considering contributing to ParcelTrack! It's people like you that make open-source software such a great community.

This document provides guidelines for contributing to the project.

## How to Contribute

We welcome contributions in many forms, from bug reports and feature requests to code changes.

### Pull Request Workflow

1.  **Fork the repository** to your own GitHub account.
2.  **Create a new branch** for your changes (e.g., `feature/add-new-shipper` or `fix/email-rendering-bug`).
3.  **Make your changes** in your feature branch.
4.  **Ensure your code follows the coding standards.**
5.  **Run the tests** to make sure you haven't introduced any regressions.
6.  **Commit your changes** with a clear and descriptive commit message.
7.  **Push your branch** to your fork.
8.  **Submit a Pull Request** to the `master` branch of the `CrazyHenk44/parcel-track` repository. Please provide a clear description of the problem and your solution.

## Coding Standards

To ensure the codebase is consistent and easy to read, we follow the **PSR-12** coding standard.

*   **Code Formatting**: Please ensure your code adheres to PSR-12. You can use tools like `php-cs-fixer` to automatically format your code.
*   **Clarity**: Write clear, understandable code with comments where necessary to explain complex logic.

## Running Tests

The project includes a simple test suite to verify the functionality of the API endpoints and shipper data parsing. These are "snapshot" tests that compare the current API output against a known-good version.

Before submitting a pull request, please run the full test suite to ensure your changes haven't broken existing functionality.

```bash
vendor/bin/phpunit tests
```

It is also run automatically with the docker build.

## Development Tools

### Inspecting Package Data with `status.py`

The project includes a helpful command-line tool, `status.py`, for quickly inspecting the state of your tracked packages. It's particularly useful for debugging.

You can run it inside the running `app` container:

```bash
docker-compose exec app python3 /opt/parceltrack/developer/status.py
```

From the detail view, press `j` to view the **raw JSON response** from the shipper's API. This is invaluable for understanding the data structure and debugging parsing issues.

## Local Development Setup

If you are contributing to ParcelTrack and need to build the Docker image locally for development or testing, follow these steps:

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

3.  **Use `docker-compose-build.yml` for local development:**
    To mount your local source code into the container for live development, you'll need to temporarily modify `docker-compose-build.yml`. Uncomment the line if you needed, then run:

    ```bash
    docker compose -f docker-compose-build.yml build
    ```

    This will build the image locally and run the containers with your local source code mounted, allowing for real-time changes during development.

---

We look forward to your contributions!
