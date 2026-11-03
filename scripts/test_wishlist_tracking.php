<?php
declare(strict_types=1);
require_once __DIR__ . '/../modules/wishlist/WishlistService.php';

function wishCheck(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function wishReject(callable $call, string $message): void {
    try { $call(); } catch (DomainException $e) { return; }
    throw new RuntimeException($message);
}

wishCheck(WishlistService::money('0', 'Price') === '0.00', 'A free offer must remain a valid zero price.');
wishCheck(WishlistService::money('', 'Price') === null, 'An unknown price must stay unknown.');
foreach (['-1','1e3','12.345','NaN','1,000','1000000000000'] as $invalid) wishReject(fn() => WishlistService::money($invalid, 'Price'), 'Invalid money accepted: ' . $invalid);
wishReject(fn() => WishlistService::validateItem(['name'=>'Stamp','status'=>'published']), 'Unexpected status accepted.');
wishReject(fn() => WishlistService::validateItem(['name'=>'Stamp','target_price'=>'12']), 'Target price without currency accepted.');
wishCheck(WishlistService::validateItem(['name'=>' Stamp ','status'=>'wanted'])['status'] === 'searching', 'Legacy status mapping failed.');
$validOffer = ['name'=>'Seller A','url'=>'https://seller.example/stamp?a=1&b=2','amount'=>'100','currency'=>'inr','shipping'=>'10','availability'=>'available','last_checked'=>'2020-02-29','notes'=>'Private'];
foreach (['javascript:alert(1)','file:///etc/passwd','ftp://seller.example/file','https://user:password@seller.example/item'] as $invalid) wishReject(fn() => WishlistService::validateOffers([array_replace($validOffer, ['url'=>$invalid])]), 'Unsafe URL accepted: ' . $invalid);
wishReject(fn() => WishlistService::validateOffers([array_replace($validOffer, ['last_checked'=>'2025-02-30'])]), 'Invalid calendar date accepted.');
wishReject(fn() => WishlistService::validateOffers([array_replace($validOffer, ['last_checked'=>'2999-01-01'])]), 'Future checked date accepted.');
wishReject(fn() => WishlistService::validateOffers([array_replace($validOffer, ['currency'=>''])]), 'Seller amount without currency accepted.');
wishReject(fn() => WishlistService::validateOffers([array_replace($validOffer,['id'=>'1']),array_replace($validOffer,['id'=>'1'])]), 'Duplicate offer ID accepted.');
$offers = [
    ['id'=>1,'amount'=>'100.00','currency'=>'INR','shipping'=>'20.00','availability'=>'available','archived'=>0,'store_name'=>'A'],
    ['id'=>2,'amount'=>'110.00','currency'=>'INR','shipping'=>'0.00','availability'=>'available','archived'=>0,'store_name'=>'B'],
    ['id'=>3,'amount'=>'1.00','currency'=>'USD','shipping'=>'0.00','availability'=>'available','archived'=>0,'store_name'=>'C'],
    ['id'=>4,'amount'=>'0.00','currency'=>'INR','shipping'=>null,'availability'=>'available','archived'=>0,'store_name'=>'Unknown shipping'],
    ['id'=>5,'amount'=>'1.00','currency'=>'INR','shipping'=>'0.00','availability'=>'sold','archived'=>0,'store_name'=>'Sold'],
    ['id'=>6,'amount'=>'1.00','currency'=>'INR','shipping'=>'0.00','availability'=>'available','archived'=>1,'store_name'=>'Removed'],
];
$best = WishlistService::cheapestOffers($offers);
wishCheck($best['INR']['id'] === 2 && $best['USD']['id'] === 3 && count($best) === 2, 'Best delivered offers must include shipping and separate currencies.');

// SQLite executes the real transactional CRUD logic; remove only MySQL row-lock syntax.
final class WishlistFixturePDO extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options); }
}
$pdo = new WishlistFixturePDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec('PRAGMA foreign_keys=ON');
$pdo->exec('CREATE TABLE module_wishlist_items (id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT,description TEXT,theme_id INTEGER,status TEXT,item_type TEXT,priority INTEGER,catalogue_ref TEXT,target_price TEXT,target_currency TEXT,image_url TEXT,collection_item_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
$pdo->exec('CREATE TABLE module_wishlist_stores (id INTEGER PRIMARY KEY AUTOINCREMENT,wishlist_item_id INTEGER REFERENCES module_wishlist_items(id) ON DELETE CASCADE,store_name TEXT,store_url TEXT,price TEXT,notes TEXT,amount TEXT,currency TEXT,shipping TEXT,availability TEXT,last_checked TEXT,archived INTEGER DEFAULT 0,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
$pdo->exec('CREATE TABLE module_wishlist_price_history (id INTEGER PRIMARY KEY AUTOINCREMENT,store_id INTEGER REFERENCES module_wishlist_stores(id) ON DELETE CASCADE,amount TEXT,currency TEXT,shipping TEXT,availability TEXT,checked_on TEXT,recorded_at TEXT DEFAULT CURRENT_TIMESTAMP)');
$service = new WishlistService($pdo);
wishCheck($service->schemaReady(), 'Fixture schema is not recognized.');
$input = ['name'=>'Wanted stamp','description'=>'Private collecting notes','status'=>'searching','item_type'=>'stamp','target_price'=>'95','target_currency'=>'INR','stores'=>[$validOffer]];
$itemId = $service->save($input, 'reference.png');
$offerId = (int)$pdo->query('SELECT id FROM module_wishlist_stores')->fetchColumn();
wishCheck((int)$pdo->query('SELECT COUNT(*) FROM module_wishlist_price_history')->fetchColumn() === 1, 'An initial offer observation must be saved.');
$edit = array_replace($input, ['id'=>(string)$itemId,'stores'=>[array_replace($validOffer,['id'=>(string)$offerId])]]);
$service->save($edit, null);
wishCheck((int)$pdo->query('SELECT COUNT(*) FROM module_wishlist_price_history')->fetchColumn() === 1, 'An unchanged offer must not create a duplicate price observation.');
wishCheck($pdo->query('SELECT image_url FROM module_wishlist_items')->fetchColumn() === 'reference.png', 'Editing without an image must preserve the existing server-side image.');
$edit['stores'][0]['amount'] = '90';
$service->save($edit, null);
wishCheck((int)$pdo->query('SELECT COUNT(*) FROM module_wishlist_price_history')->fetchColumn() === 2, 'A changed price must create a new observation.');
wishCheck((int)$pdo->query('SELECT id FROM module_wishlist_stores')->fetchColumn() === $offerId, 'Editing must retain the original offer ID.');
$otherId = $service->save(['name'=>'Other cover','item_type'=>'cover'], null);
$wrongOwner = array_replace($edit, ['id'=>(string)$otherId,'name'=>'Must roll back']);
wishReject(fn() => $service->save($wrongOwner, null), 'Cross-item seller ID accepted.');
wishCheck($pdo->query('SELECT name FROM module_wishlist_items WHERE id=' . $otherId)->fetchColumn() === 'Other cover', 'A failed offer ownership check must roll back item changes.');
$service->save(array_replace($edit, ['stores'=>[]]), null);
wishCheck((int)$pdo->query('SELECT archived FROM module_wishlist_stores WHERE id=' . $offerId)->fetchColumn() === 1, 'Removing an offer must archive it.');
wishCheck((int)$pdo->query('SELECT COUNT(*) FROM module_wishlist_price_history')->fetchColumn() === 2, 'Removing an offer must retain price history.');
$items = $service->all();
$wanted = array_values(array_filter($items, fn($item) => (int)$item['id'] === $itemId))[0];
wishCheck(count($wanted['stores'][0]['history']) === 2 && !$wanted['best'], 'Archived observations must remain readable and must not enter best-price comparisons.');
echo "Wishlist validation, price comparison, transaction rollback, stable offers, and price history checks passed.\n";
