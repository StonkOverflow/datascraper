FROM python:3.8

# if we plan on exposing a port like through flask:
# EXPOSE 5000

# Sets the working directory for following COPY and CMD instructions
# Notice we haven’t created a directory by this name - this instruction 
# creates a directory with this name if it doesn’t exist
WORKDIR /app

# Install any needed packages specified in the Pipfiles
# I send both the Pipfile and Pipfile.lock over so theres no locking time.
COPY Pipfile* /app/
RUN pip install pipenv && \
    pipenv install --system --deploy --dev

# In the future we will likely have more files/ folders to send instead
COPY forex_stream.py /app
