import csv
import time

from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.common.exceptions import TimeoutException
from selenium.webdriver.common.desired_capabilities import DesiredCapabilities

TIMEOUT = 20

options = webdriver.ChromeOptions()

browser = webdriver.Remote("http://chrome:4444/wd/hub", DesiredCapabilities.CHROME, options=options)

wait = WebDriverWait(browser, 10)

browser.get('https://otctransparency.finra.org/otctransparency/AtsIssueData')
wait.until(EC.element_to_be_clickable((By.CLASS_NAME, 'btn-warning')))

browser.find_element_by_class_name('btn-warning').click()
wait.until(EC.presence_of_element_located((By.TAG_NAME, 'input')))

search_field = browser.find_element_by_tag_name('input')

def get_symbol_data(symbol):
    search_field.clear()
    time.sleep(0.5)

    search_field.send_keys(symbol)
    time.sleep(0.5)

    data = browser.find_elements_by_xpath('//div[@role="row"]')
    reader = csv.reader([d.text.replace('\n', '*') for d in data], delimiter='*')
    for row in reader:
        if row[0] == symbol:
            return {'total shares': row[2], 'total trades': row[3], 'last updated': row[4]}

if __name__ == '__main__':
    symbols = ['SPY', 'VOO', 'IVV']
    symbol_data = {symbol: get_symbol_data(symbol) for symbol in symbols}
    print(symbol_data)
