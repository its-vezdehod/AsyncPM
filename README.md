# AsyncPM

Yet another one promise implementation with async/await using both generators and fibers (Designed for PMMP plugins usage)

# Warning
Work in progress!

# Resolving

```php
/**
 * @return Promise<LifeBoat>
 */
public function getLifeBoatAsync(): Promise {
    $resolver = new PromiseResolver();
    $resolver->fulfill(new LifeBoat(name: "sg.lbsg.net", balance: Currency::USD(5000.00)));
    // Or
    $resolver->reject(new Exception("LifeBoat is offline!"));
    return $resolver->promise();
}
```

# Working with promises
You can chain promises using then and catch
```php
$promise = CatFactory::createAsync()->then(function(Cat $cat) {
    $cat->setName("Dylan K. Taylor");
    $cat->setUsername("dktapps");
    $cat->setProject("github.com/pmmp");
    $cat->setBlameTarget("shoghi");
    
    return new Dktapps(source: $cat, balance: Currency::USD(0.00));
});
```

Also, you can handle result using onCompletion.
```php
$promise->onCompletion(function(?Dktapps $dylan, ?Throwable $err): void {
    if ($err !== null) {
        die($err->getMessage());
    }
    $dylan->getRandomPR()->close(PRCloseReasonFactory::random());
});
```

# async/await
then and catch support async/await!

Using coroutines:
```php
$promise->then(function(Dktapps $dylan): \Generator {
    (yield $this->getLifeBoatAsync())->pay($dylan, Currency::USD(5000.00));
    return new Feature("Modern world format support!");
});
```

Using fibers (closure attributed with ```#[Async]```):
```php
$promise->then(#[Async] function(Dktapps $dylan): Feature {
    await($this->getLifeBoatAsync())->pay($dylan, Currency::USD(5000.00));
    // Or
    Promise::await(...)->pay(...);
    return new Feature("Moder world format support!");
});
```

# AsyncState
Make sure that you still in valid state easily

```php
$promise->then(#[Async] function(SomeAPIResponse $response, AsyncState $state) use ($player, $sender, $amount): bool {
    $state->requires($player->isOnline(...), "Player left the game");
    $state->requires($sender->isOnline(...), "Sender left the game");
    
    $reachDailyLimit = await($this->checkWholeNetworkMoneyLimit($sender));
    // After this ^ promise awaited your state will be validated
    if ($reachDailyLimit) {
        throw new Exception("You reached daily limit for money");
    }
    
    $isSuspicious = await(SuspiciousMoneyTransactionsDetector::detect($sender));
    // Also, after this ^ promise awaited your state will be validated    
    // ...
        
    return await($this->transferMoney($sender, $player, $amount));
})->onCompletion(function(?bool $value, ?Throwable $err) use ($sender, $player) {
    if (!($sender instanceof Player && $sender->isOnline())) {
        return;
    }
    if ($err !== null) {
        $sender->sendMessage("Error: {$err->getMessage()}");    
        return;
    }
    // Handle...
});

```