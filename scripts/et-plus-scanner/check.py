#!/usr/bin/env python


import csv
import json
import sys
import threading
from queue import Queue


from bs4 import BeautifulSoup
import requests


OUTPUT = csv.writer(sys.stdout)
PRINT_LOCK = threading.Lock()

def process_site(url):
    try:
        response = requests.get(url)
        response.raise_for_status()

        soup = BeautifulSoup(response.text, features="html.parser")

        has_etp_link = len(soup.find_all(id='event-tickets-plus-tickets-css-css'))
        has_woo_link = len(soup.find_all(id='woocommerce-layout-css'))

        if has_etp_link and has_woo_link:
            tickets_response = requests.get(url + '/wp-json/tribe/tickets/v1/tickets/')
            try:
                tickets = json.loads(tickets_response.text)
                for ticket in tickets['tickets']:
                    if ticket['provider'] == 'woo' and ticket['available_until_details']['year'] != '' and int(ticket['available_until_details']['year']) == 2019:
                        gross_sales = float(ticket['cost'].replace('$', '').replace(',', '')) * len(ticket['attendees'])
                        with PRINT_LOCK:
                            OUTPUT.writerow([url, ticket['title'], ticket['date'], ticket['cost'], len(ticket['attendees']), gross_sales, ticket['capacity_details']])
            except Exception as exc:
                with PRINT_LOCK:
                    sys.stderr.write("Error processing %s: %s\n" % (url, str(exc),))

    except Exception as exc:
        with PRINT_LOCK:
            sys.stderr.write("Error processing %s: %s\n" % (url, str(exc),))


def process_queue():
    while True:
        current_url = url_queue.get()
        process_site(current_url)
        url_queue.task_done()


if __name__ == "__main__":
    OUTPUT.writerow(['Site', 'Title', 'Date', 'Cost', 'Attendee Count', 'Gross Sales', 'Capacity Details'])

    url_queue = Queue()

    for i in range(5):
        t = threading.Thread(target=process_queue)
        t.daemon = True
        t.start()

    with open(sys.argv[1], 'r') as input_file:
        for line in input_file:
            line = line.strip()
            url_queue.put(line)

    url_queue.join()
