# datascraper

for scraping data

# Install Docker

First follow the steps to install the [engine](https://docs.docker.com/engine/install/)
Then follow [these instructions](https://docs.docker.com/engine/install/linux-postinstall/) so you dont have to sudo everything
Finally add docker compose using [these instructions](https://docs.docker.com/compose/install/)

# Using Docker

Start up the python and mysql environments by running `docker-compose up`
To get inside the python bash run `docker-compose exec app bash`
To get inside the mysql db run `docker-compose exec db mysql`

### Other docker things

To see whats running do `docker ps`.
To rebuild images from scratch do
`docker stop`
`docker rm "container id"`
`docker-compose up`

# Running things locally

To get the python things you need, first `pip install pipenv` then `pipenv install && pipenv shell`

To run mysql commands locally make sure you install the mysql-server first.

### For darkpools

Make sure you have the selenium chrome webdriver installed and on the path
