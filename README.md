# Ledger App

This is a prototype ledger app - not meant for any kind of production usage.

# Assumptions

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

# Routes

### POST localhost/api/income
Allowed parameters
```
    "amount": float,                         // dollar value of transactions
    "date": string,                          // [OPTIONAL] datetime of transaction - leaving blank will default to now() when processed
    "description": string,                   // [OPTIONAL] brief description of the transaction
    "ledger_id": int,                        // the ledger being posted to - this 
    "frequency": string,                     // [OPTIONAL] if this is a recurring transaction, how often it should be processed. Can be 'weekly','fortnightly','monthly','yearly'
    "end_date": string                       // [OPTIONAL - BUT REQUIRED IF FREQUENCY IS GIVEN] when the recurring transaction will finish
```
Example Body:
`localhost/api/income`
```json
{
    "amount": 100.00,
    "date": "2025-10-04 15:40",
    "description": "test transfer",
    "ledger_id": 1,
    "from_ledger_id":2,
    "frequency": "weekly",
    "end_date": "2026-10-04 15:40"
}
```
Example Response:
`201 created`
```json
{
    "message": "Income recorded.",
    "data": {
        "transaction": {
            "ledger_id": 1,
            "amount": 300,
            "occurred_at": "2025-10-04T15:40:00.000000Z",
            "description": "test transfer",
            "type": "credit",
            "updated_at": "2025-10-04T14:43:35.000000Z",
            "created_at": "2025-10-04T14:43:35.000000Z",
            "id": 5663
        },
        "linked_expense": {
            "ledger_id": 2,
            "amount": -300,
            "occurred_at": "2025-10-04T15:40:00.000000Z",
            "description": "Internal transfer from test transfer",
            "type": "debit",
            "updated_at": "2025-10-04T14:43:35.000000Z",
            "created_at": "2025-10-04T14:43:35.000000Z",
            "id": 5662
        }
    }
}
```

### POST localhost/api/expense
Allowed body parameters
```
    "amount": float,                         // dollar value of transactions
    "date": string,                          // [OPTIONAL] datetime of transaction - leaving blank will default to now() when processed
    "description": string,                   // [OPTIONAL] brief description of the transaction
    "ledger_id": int,                        // the ledger being posted to - this 
    "frequency": string,                     // [OPTIONAL] if this is a recurring transaction, how often it should be processed. Can be 'weekly','fortnightly','monthly','yearly'
    "end_date": string                       // [OPTIONAL - BUT REQUIRED IF FREQUENCY IS GIVEN] when the recurring transaction will finish
```
Example body:
`localhost/api/expense`
```json
{
    "amount": 100.00,
    "date": "2025-10-04 15:40",
    "description": "App subscription",
    "ledger_id": 1,
    "frequency": "weekly",
    "end_date": "2026-10-04 15:40"
}
```
Example response:
`201 Created`
```json
{
    "message": "Expense recorded.",
    "transaction": {
        "ledger_id": 1,
        "amount": -100,
        "occurred_at": "2025-10-04T15:40:00.000000Z",
        "description": "test transfer",
        "type": "debit",
        "updated_at": "2025-10-04T14:43:13.000000Z",
        "created_at": "2025-10-04T14:43:13.000000Z",
        "id": 5661
    }
}
```



### GET localhost/api/transactions
I never got round to adding parameters, this will just dump the entire transaction list to the api endpoint.

Example Response:
`200 success`
```json
{
    "count": 1921,
    "data": [
        {
            "id": 4282,
            "ledger_id": 2,
            "business": "Joes Flooring",
            "entity": "Joes Flooring Moonta",
            "ledger": "Joes Flooring Moonta Services",
            "type": "debit",
            "amount": -954.42,
            "description": "Subscription",
            "occurred_at": "2025-10-04T17:35:23+00:00"
        },
        {
            "id": 4643,
            "ledger_id": 7,
            "business": "Joes Carpet Cleaning",
            "entity": "Joes Carpet Cleaning Mile End",
            "ledger": "Joes Carpet Cleaning Mile End Revenue",
            "type": "credit",
            "amount": 2326.35,
            "description": "Sale: quas pariatur",
            "occurred_at": "2025-10-04T16:00:59+00:00"
        },
        ...
    ]
}
```


### GET localhost/api/forecast
Allowed query parameters
```
lookahead_months: int                         // [OPTIONAL] (default 12) how many months to look ahead into the future for a forecast. Will be less accurate long-term as recurring transactions will fall off as their end-dates are met.
as_at: string                                 // [OPTIONAL] 
```

Example response:
`localhost/api/forecast?lookahead_months=3`
```json
{
    "as_at": "2025-10-04T14:47:21+00:00",
    "until": "2025-12-31T23:59:59+00:00",
    "data": [
        {
            "business_id": 1,
            "business": "Joes Flooring",
            "entities": [
                {
                    "entity_id": 1,
                    "entity": "Joes Flooring Moonta",
                    "ledgers": [
                        {
                            "ledger_id": 1,
                            "ledger": "Joes Flooring Moonta Revenue",
                            "opening_balance_at_as_at": 654578.98,
                            "projected_balance_at_until": 640068.33,
                            "projected_change": -14510.65,
                            "monthly": [
                                {
                                    "month": "2025-10",
                                    "recurring_total": -9615.4,
                                    "historical_total": 11142.46,
                                    "projected_change": 1527.06
                                },
                                {
                                    "month": "2025-11",
                                    "recurring_total": -8247.33,
                                    "historical_total": 0,
                                    "projected_change": -8247.33
                                },
                                {
                                    "month": "2025-12",
                                    "recurring_total": -7790.38,
                                    "historical_total": 0,
                                    "projected_change": -7790.38
                                }
                            ]
                        },
                        {
                            "ledger_id": 2,
                            "ledger": "Joes Flooring Moonta Services",
                            "opening_balance_at_as_at": 53327.71,
                            "projected_balance_at_until": 38001.7,
                            "projected_change": -15326.01,
                            "monthly": [
                                {
                                    "month": "2025-10",
                                    "recurring_total": -4621.68,
                                    "historical_total": -1081.85,
                                    "projected_change": -5703.53
                                }, ...
                            ]
                        }, ...
                ]
            }, ...
```

I never got round to adding the parameters I was planning here - but this gets all the businesses in the system, breaks them down into their entities (in this example, it's just different sites).

The projected change is just a sum of all the months. Each month will check all the recurring transactions that will fall on the month, and sum them. This is the `recurring_total` property you see.

The `historical_total` property is just the amount of money found in transactions in a sample month previously. It's not a very thorough way of forecasting, but its logic is separated and easy to upgrade if needed.

The month-by-month data is kept in the response, in case it's needed for tabling or graphs.

### GET localhost/api/balance

This just groups up all the businesses, all the entities in those businesses and all their ledgers, and displays the current balance as at endpoint call.

Example response:
```json
{
    "as_of": "2025-10-04T14:54:58+00:00",
    "data": [
        {
            "business_id": 1,
            "business": "Joes Flooring",
            "entities": [
                {
                    "entity_id": 1,
                    "entity": "Joes Flooring Moonta",
                    "ledgers": [
                        {
                            "ledger_id": 1,
                            "ledger": "Joes Flooring Moonta Revenue",
                            "current_balance": 634638.5499999996
                        },
                        {
                            "ledger_id": 2,
                            "ledger": "Joes Flooring Moonta Services",
                            "current_balance": -21960.07
                        },
                        {
                            "ledger_id": 3,
                            "ledger": "Joes Flooring Moonta Payroll",
                            "current_balance": -16214.059999999998
                        }
                    ]
                },
                {
                    "entity_id": 2,
                    "entity": "Joes Flooring Plympton",
                    "ledgers": [
                        {
                            "ledger_id": 4,
                            "ledger": "Joes Flooring Plympton Revenue",
                            "current_balance": 290328.1600000001
                        },
                        {
                            "ledger_id": 5,
                            "ledger": "Joes Flooring Plympton Services",
                            "current_balance": -19504.810000000005
                        },
                        {
                            "ledger_id": 6,
                            "ledger": "Joes Flooring Plympton Payroll",
                            "current_balance": -24020.479999999996
                        }
                    ]
                }
            ]
        },
        {
            "business_id": 2,
            "business": "Joes Carpet Cleaning",
            "entities": [
                {
                    "entity_id": 3,
                    "entity": "Joes Carpet Cleaning Mile End",
                    "ledgers": [
                        {
                            "ledger_id": 7,
                            "ledger": "Joes Carpet Cleaning Mile End Revenue",
                            "current_balance": 248755.51000000024
                        },
                        {
                            "ledger_id": 8,
                            "ledger": "Joes Carpet Cleaning Mile End Services",
                            "current_balance": -31210.08999999996
                        },
                        {
                            "ledger_id": 9,
                            "ledger": "Joes Carpet Cleaning Mile End Payroll",
                            "current_balance": -828.8600000000006
                        }
                    ]
                }
            ]
        },
        {
            "business_id": 3,
            "business": "Joes Mowing",
            "entities": [
                {
                    "entity_id": 4,
                    "entity": "Joes Mowing Pt Lincoln",
                    "ledgers": [
                        {
                            "ledger_id": 10,
                            "ledger": "Joes Mowing Pt Lincoln Revenue",
                            "current_balance": 294767.57999999984
                        },
                        {
                            "ledger_id": 11,
                            "ledger": "Joes Mowing Pt Lincoln Services",
                            "current_balance": -19082.319999999985
                        },
                        {
                            "ledger_id": 12,
                            "ledger": "Joes Mowing Pt Lincoln Payroll",
                            "current_balance": -24796.96
                        }
                    ]
                }
            ]
        },
        {
            "business_id": 4,
            "business": "Joes Consulting",
            "entities": [
                {
                    "entity_id": 5,
                    "entity": "Joes Consulting Adelaide",
                    "ledgers": [
                        {
                            "ledger_id": 13,
                            "ledger": "Joes Consulting Adelaide Revenue",
                            "current_balance": 374604.99999999994
                        },
                        {
                            "ledger_id": 14,
                            "ledger": "Joes Consulting Adelaide Services",
                            "current_balance": -26532.58000000001
                        },
                        {
                            "ledger_id": 15,
                            "ledger": "Joes Consulting Adelaide Payroll",
                            "current_balance": -34445.26
                        }
                    ]
                }
            ]
        }
    ]
}
```

