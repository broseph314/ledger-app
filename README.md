# Ledger App

This is a prototype ledger app - not meant for any kind of production usage.

# How to run

I used laravel sail - I find it easy and quick to spin up all the things you need. So to recreate the code and get this up and running, you need a couple of things:
- Docker engine running
- PHP & Composer installed

If you don't have these things installed, go check out Laravel's [installation docs](https://laravel.com/docs/12.x/installation) for the initialisation and installation scripts, and then look at the laravel Sail [documentation](https://laravel.com/docs/12.x/sail).

First, get all the bits and pieces installed that you'll need for Sail:

> clone the repository and run scripts from the base directory

```bash
php artisan sail:install
```

You'll only need a MySQL database for this to work. This will take a few minutes to get finished.

Now you need to run the sail instance - this will spin up some docker containers to run your laravel app - with a quick nginx container, and a MySQL database.

> I like to alias my `./vendor/bin/sail` command to just `sail` in bash - which there's a nice script for in the laravel docs if you're interested

```
./vendor/bin/sail up -d
```
> The detached `-d` flag will run the docker compose script without needing the terminal open. You might actually want to omit this option if you want to see request logging in the terminal.

Next, get all the migrations sorted so that you've got all the tables ready in the database:
```bash
./vendor/bin/sail artisan migrate
```

This should create all the necessary tables in the database for the app to run. 

Now finally before you check out the code, make sure to open a new terminal in the base directory, and run `npm run dev` to run a development Vite server to bundle your assets without needing to build anything. Keep this running when you're interacting with the frontend.

The app should now be running - and if you visit `localhost` in a browser, you should get the Laravel starter kit screen. 

## Seeding the Database

Before the app endpoints will actually show anything, make sure you run `./vendor/bin/sail artisan db:seed` - this will generate some test data - a few businesses, some attached entities, ledgers, and then a few thousand transactions for test data.

At this point, you should be able to interact with all the endpoints in the `routes/api.php` file.

## Routes

### POST localhost/api/income
### POST localhost/api/expense
### GET localhost/api/transactions
### GET localhost/api/forecast
### GET localhost/api/balance



