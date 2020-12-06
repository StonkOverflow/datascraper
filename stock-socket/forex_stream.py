import json
import os
import csv
import time
from websocket import create_connection

HEADERS = {
    "User-Agent": "Mozilla/5.0 (X11; Linux x86_64; rv:82.0) Gecko/20100101 Firefox/82.0",
    "Accept": "*/*",
    "Accept-Language": "en-US,en;q=0.5",
    "Sec-WebSocket-Version": "13",
    "Sec-WebSocket-Key": "XwcSn0aNlocx79iSbvT7LQ==",
    "Pragma": "no-cache",
    "Cache-Control": "no-cache"
}

URL = 'wss://stream274.forexpros.com/echo/211/24okwg7g/websocket'

HANDSHAKE_PARTS = [
    ['{\"_event\":\"bulk-subscribe\",\"tzID\":\"8\",\"message\":\"pid-1:\"}'],
    ['{"_event":"UID","UID":0}']
]

HEARTBEAT_MESSAGE = ['{\"_event\":\"heartbeat\",\"data\":\"h\"}']

class ForexStream:
    def __init__(self):
        self.ws = create_connection(URL, headers=HEADERS)
        if self.ws.recv() != 'o':
            raise ValueError('could not connect')

        self.perform_handshake()

    def perform_handshake(self):
        for part in HANDSHAKE_PARTS:
            self.send_message(part)

    def send_message(self, message):
        self.ws.send(json.dumps(message))
        resp = self.ws.recv()
        if ("No response from heartbeat" in resp):
            print(resp)
            self.ws.shutdown()
            time.sleep(3)
            self.__init__()
            return self.send_message(HEARTBEAT_MESSAGE)
        else:
            return self.process_response(resp)

    def process_response(self, message):
        return json.loads(
            message[3:-2].replace(
                '\\"', '"'
            ).replace(
                '\\\\', '\\'
            )
        )

    def maybe_get_message_json(self, data):
        if 'message' not in data:
            return None

        return json.loads(data['message'].split('::')[1])

    def start_stream(self):
        while True:
            data = self.maybe_get_message_json(self.send_message(HEARTBEAT_MESSAGE))
            if data is not None:
                yield data

            time.sleep(.1)

    @staticmethod
    def make_writer(file, first_row):
        writer = csv.DictWriter(file, first_row.keys())
        if os.stat(file.name).st_size == 0:
            writer.writeheader()

        writer.writerow(first_row)
        return writer

    def stream_to_csv(self, filename='test.csv'):
        stream = self.start_stream()
        with open(filename, 'a') as f:
            writer = self.make_writer(f, next(stream))
            for row in stream:
                writer.writerow(row)

if __name__ == '__main__':
    try:
        stream = ForexStream()
        stream.stream_to_csv()
    except json.JSONDecodeError:
        print("connection lost")
    except ValueError:
        print("could not connect")
    except TimeoutError:
        print("connection lost")
