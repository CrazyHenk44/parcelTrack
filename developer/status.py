import curses
import os
import json
import time
from datetime import datetime, timezone

DATA_DIR = '/opt/parceltrack/data/'

def get_latest_event_timestamp(package):
    """
    Returns the Unix timestamp of the most recent event for a package.
    Returns 0 if no events are found.
    """
    events = package.get('events', [])
    if not events:
        return 0
    
    latest_ts = 0
    for event in events:
        try:
            latest_ts = max(latest_ts, datetime.fromisoformat(event.get('timestamp', '').replace('Z', '+00:00')).timestamp())
        except (ValueError, TypeError):
            continue
    return latest_ts

def get_packages():
    packages = []
    if not os.path.exists(DATA_DIR):
        return packages

    for filename in sorted(os.listdir(DATA_DIR)):
        if filename.endswith('.json'):
            with open(os.path.join(DATA_DIR, filename)) as f:
                try:
                    pkg = json.load(f)
                    # Read custom name from the new metadata object
                    # Ensure metadata and customName exist and are not None before assigning
                    if pkg.get('metadata') and pkg['metadata'].get('customName'):
                        pkg['customName'] = pkg['metadata']['customName']
                    packages.append(pkg)
                except json.JSONDecodeError:
                    pass
    
    # Sort packages by the timestamp of their most recent event, descending.
    return sorted(packages, key=get_latest_event_timestamp, reverse=True)

def draw_list_view(stdscr, packages, current_row):
    stdscr.clear()
    h, w = stdscr.getmaxyx()

    if not packages:
        stdscr.addstr(0, 0, "No packages found in the 'data' directory.")
        stdscr.addstr(2, 0, "Press 'q' to quit or 'r' to refresh.")
        stdscr.refresh()
        return

    header = f"{'Name / Tracking Code':<41} | {'Status'}"
    stdscr.addstr(0, 0, header, curses.A_REVERSE)

    for i, package in enumerate(packages):
        if i + 1 >= h:
            break
        shipper = package.get('shipper', 'N/A')
        tracking_code = package.get('trackingCode', 'N/A')
        display_name = package.get('customName') or f"{shipper} - {tracking_code}"
        status = package.get('status', 'N/A')
        line = f"{display_name:<41} | {status}"
        if i == current_row:
            stdscr.addstr(i + 1, 0, line[:w-1], curses.A_REVERSE)
        else:
            stdscr.addstr(i + 1, 0, line[:w-1])

    stdscr.addstr(h - 1, 0, "Arrows: Navigate | Enter: Details | r: Refresh | q: Quit")
    stdscr.refresh()

def draw_raw_json_view(stdscr, package):
    stdscr.clear()
    h, w = stdscr.getmaxyx()    
    display_name = package.get('customName') or package.get('trackingCode')
    stdscr.addstr(0, 0, f"Raw JSON for {display_name}", curses.A_BOLD)
    stdscr.addstr(h - 1, 0, "Arrows: Scroll | b/q: Back")
    stdscr.refresh()

    try:
        raw_json_str = package.get('rawResponse', '{}')
        raw_json = json.loads(raw_json_str)
        formatted_json = json.dumps(raw_json, indent=4).splitlines()
    except (json.JSONDecodeError, TypeError):
        stdscr.addstr(2, 0, "Error: Could not parse raw JSON.")
        stdscr.getch()
        return

    num_lines = len(formatted_json)
    max_line_length = max(len(line) for line in formatted_json) if num_lines > 0 else 0

    # Create a pad (a scrollable window)
    # Pad height is number of lines, width is max line length + 1
    pad = curses.newpad(num_lines + 1, max_line_length + 1)
    pad.keypad(True)

    for i, line in enumerate(formatted_json):
        pad.addstr(i, 0, line)

    pad_top_line = 0
    pad_left_col = 0

    while True:
        # Refresh the pad to the screen
        # (pad_top, pad_left), (screen_top, screen_left), (screen_bottom, screen_right)
        pad.refresh(pad_top_line, pad_left_col, 2, 0, h - 2, w - 1)

        key = pad.getch()
        if key == ord('q') or key == ord('b'):
            break
        elif key == curses.KEY_UP:
            pad_top_line = max(0, pad_top_line - 1)
        elif key == curses.KEY_DOWN:
            max_v_scroll = max(0, num_lines - (h - 3))
            pad_top_line = min(max_v_scroll, pad_top_line + 1)
        elif key == curses.KEY_LEFT:
            pad_left_col = max(0, pad_left_col - 4)
        elif key == curses.KEY_RIGHT:
            max_h_scroll = max(0, max_line_length - w + 1)
            pad_left_col = min(max_h_scroll, pad_left_col + 4)


def draw_detail_view(stdscr, package):
    while True:
        stdscr.clear()
        h, w = stdscr.getmaxyx()

        shipper = package.get('shipper', 'N/A')
        tracking_code = package.get('trackingCode', 'N/A')
        status = package.get('status', 'N/A')
        events = package.get('events', [])
        display_name = package.get('customName') or f"{shipper} - {tracking_code}"

        status_timestamp_str = ""
        if events:
            try:
                status_timestamp = events[0].get('timestamp', '')
                status_timestamp_str = datetime.fromisoformat(status_timestamp.replace('Z', '+00:00')).strftime('%Y-%m-%d %H:%M:%S')
            except (ValueError, TypeError):
                status_timestamp_str = events[0].get('timestamp', '')

        stdscr.addstr(0, 0, f"Details for {display_name}", curses.A_BOLD)
        stdscr.addstr(2, 0, f"Status: [{status_timestamp_str}] {status}")

        stdscr.addstr(4, 0, "Events:", curses.A_BOLD)
        if not events:
            stdscr.addstr(5, 2, "No events found.")
        else:
            for i, event in enumerate(events):
                if i + 5 >= h -1:
                    stdscr.addstr(i + 5, 2, "... (more events truncated)")
                    break
                
                timestamp = event.get('timestamp', 'N/A')
                description = event.get('description', 'N/A')
                location = event.get('location', '')

                try:
                    ts = datetime.fromisoformat(timestamp.replace('Z', '+00:00')).strftime('%Y-%m-%d %H:%M:%S')
                except (ValueError, TypeError):
                    ts = timestamp

                location_str = f" @ {location}" if location else ""
                line = f"- [{ts}] {description}{location_str}"
                stdscr.addstr(i + 5, 2, line[:w-1])

        stdscr.addstr(h - 1, 0, "b/q: Back | j: Show Raw JSON")
        stdscr.refresh()

        key = stdscr.getch()
        if key == ord('q') or key == ord('b'):
            break
        elif key == ord('j'):
            draw_raw_json_view(stdscr, package)


def main(stdscr):
    curses.curs_set(0)
    current_row = 0
    packages = get_packages()

    while True:
        draw_list_view(stdscr, packages, current_row)
        key = stdscr.getch()

        if key == ord('q'):
            break
        elif key == ord('r'):
            packages = get_packages()
            current_row = 0
        elif key == curses.KEY_UP:
            current_row = max(0, current_row - 1)
        elif key == curses.KEY_DOWN:
            if packages:
                current_row = min(len(packages) - 1, current_row + 1)
        elif key == curses.KEY_ENTER or key in [10, 13]:
            if packages:
                draw_detail_view(stdscr, packages[current_row])

if __name__ == "__main__":
    curses.wrapper(main)
