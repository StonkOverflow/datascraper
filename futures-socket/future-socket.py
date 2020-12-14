#future-socket.py
"""
@author: Kevin Meyers, Nikhil Devanathan
Code to pull data from the tvc.forexpros.com websocket for real time futures data
"""

#default packages
import json
import os
import csv
import time

#installed packages
from websocket import create_connection

#HTTP request headers for websocket connection
HEADERS = {
    "User-Agent": "Mozilla/5.0 (X11; Linux x86_64; rv:82.0) Gecko/20100101 Firefox/82.0",
    "Accept": "*/*",
    "Accept-Language": "en-US,en;q=0.5",
    "Sec-WebSocket-Version": "13",
    "Sec-WebSocket-Key": "XwcSn0aNlocx79iSbvT7LQ==",
    "Pragma": "no-cache",
    "Cache-Control": "no-cache"
}

#Websocket url
URL = 'wss://stream274.forexpros.com/echo/211/24okwg7g/websocket'

#Websocket handshake data
HANDSHAKE_PARTS = [
    ['{\"_event\":\"bulk-subscribe\",\"tzID\":\"8\",\"message\":\"pid-1:\"}'],
    ['{"_event":"UID","UID":0}']
]

#Map interal PID values to common ticker keys
PID_DICT = {
    "CHINA50": "44486",
    "UK100": "8838",
    "DE30": "8826",
    "JP225": "8859",
    "US500": "8839",
    "US2000": "8864",
    "US30": "8873",
    "USTEC": "8874",
    "AUD/JPY": "49",
    "AUD/USD": "5",
    "EUR/GBP": "6",
    "EUR/JPY": "9",
    "EUR/USD": "1",
    "GBP/JPY": "11",
    "GBP/USD": "1",
    "USD/CAD": "7",
    "USD/CHF": "4",
    "USD/JPY": "3",
    "HG": "8831", #Copper
    "ZC": "8918", #Corn
    "T": "8849", #Crude oil
    "ZG": "8830", #Gold
    "NG": "8862", #Natural gas
    "ZI": "8836", #Silver
    "ZS": "8916", #Soybeans
}

#Message used to change the monitored future
SUBSCRIBE_MESSAGE = '{\"_event\":\"bulk-subscribe\",\"tzID\":\"8\",\"message\":\"pid-%s:\"}'

#Message used to keep websocket connection alive
HEARTBEAT_MESSAGE = ['{\"_event\":\"heartbeat\",\"data\":\"h\"}']

#Class for a data pipline from tvc.forexpros.com websocket
class ForexStream:
    #Initializes websocket and performs handshake
    def __init__(self):
        self.ws = create_connection(URL, headers=HEADERS)
        if self.ws.recv() != 'o':
            raise ValueError('could not connect')

        self.perform_handshake()

    #Sends the handshake over websocket connection
    def perform_handshake(self):
        for part in HANDSHAKE_PARTS:
            self.send_message(part)

    #Sends the provided message over the websocket connection. Handles reconnecting if connection drops
    def send_message(self, message):
        self.ws.send(json.dumps(message))
        resp = self.ws.recv()
        if ("No response from heartbeat" in resp):
            print(message)
            self.ws.shutdown()
            self.__init__()
            return self.send_message(HEARTBEAT_MESSAGE)
        else:
            return self.process_response(resp)

    #Converts given ticker to a PID the server recognizes. Sends the PID over the websocket connection
    def change(self, ticker):
        if (ticker in PID_DICT):
            change_message = SUBSCRIBE_MESSAGE % PID_DICT[ticker.upper().strip()]
            self.send_message([change_message])
        else:
            print("Unknown ticker")

    #Reads the websocket response to any sent message
    def process_response(self, message):
        return json.loads(
            message[3:-2].replace(
                '\\"', '"'
            ).replace(
                '\\\\', '\\'
            )
        )

    #Loads any provided non-null json data
    def maybe_get_message_json(self, data):
        if 'message' not in data:
            return None

        return json.loads(data['message'].split('::')[1])
    
    #Maintains the websocket connection with a .1 second hearbeat
    def start_stream(self):
        while True:
            data = self.maybe_get_message_json(self.send_message(HEARTBEAT_MESSAGE))
            if data is not None:
                yield data

            time.sleep(.1)

    #Writes provided data to a csv file with headers
    @staticmethod
    def make_writer(file, first_row):
        writer = csv.DictWriter(file, first_row.keys())
        if os.stat(file.name).st_size == 0:
            writer.writeheader()

        writer.writerow(first_row)
        return writer

    #Records recieved data from the websocket to a csv file
    def stream_to_csv(self, filename='test.csv'):
        stream = self.start_stream()
        with open(filename, 'a') as f:
            writer = self.make_writer(f, next(stream))
            for row in stream:
                writer.writerow(row)

if __name__ == '__main__':
    #Handles some basic errors related to disconnecting from the websocket
    try:
        stream = ForexStream()
        stream.stream_to_csv()
    except json.JSONDecodeError:
        print("connection lost")
    except ValueError:
        print("could not connect")
    except TimeoutError:
        print("connection lost")