<?php
declare(strict_types=1);

/** Synthetic, disposable data used by public-biography tests and the loopback-only preview. */
function peoplePublicFixture(): PDO
{
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec('CREATE TABLE people(id INTEGER PRIMARY KEY,name TEXT,slug TEXT,birth_date TEXT,death_date TEXT,short_description TEXT,biography TEXT,infobox_data TEXT,profile_image TEXT,is_public INTEGER)');
    $pdo->exec('CREATE TABLE items(id INTEGER PRIMARY KEY,title TEXT,reg_number TEXT,production_date TEXT,is_visible INTEGER)');
    $pdo->exec('CREATE TABLE media(id INTEGER PRIMARY KEY,item_id INTEGER,file_path TEXT,media_type TEXT)');
    $pdo->exec('CREATE TABLE item_people(item_id INTEGER,person_id INTEGER,role TEXT)');
    $names = ['Asha Raman','Bela Sen','C. Rao','Dev Mehta','Ela Thomas','Farah Nair','G. Kapoor','Hari Bose','Ira Das','J. Menon','Kiran Shah','Leela Roy','100% Collector','മലയാളം','Private profile'];
    $insert = $pdo->prepare('INSERT INTO people VALUES(?,?,?,?,?,?,?,?,?,?)');
    foreach ($names as $index => $name) {
        $id = $index + 1;
        $body = "This synthetic biography is used to review the collection archive interface. Its paragraphs demonstrate comfortable line lengths, clear reading order, and links between people and collection records.\n\nA biography can connect a person with stamps, covers, correspondence, and postal cancellations. Each linked record keeps its own catalogue entry and full image. The page brings these records together without changing the underlying collection.\n\nProfile facts, dates, and photographs are supplied by the collection editor. This preview contains example content only and does not add or change any application records.";
        $facts = json_encode([['label'=>'Field','value'=>'Postal history'],['label'=>'Collection focus','value'=>'Stamps and first day covers'],['label'=>'Notes','value'=>'Synthetic interface preview, not a historical record.']]);
        $insert->execute([$id,$name,'sample-person-'.$id,$id===1?'1946':'','', $id===1?'Postal historian and collector':'Stories, people, and the history of the collection', $id===2?'':$body, $facts, $id===1?'original/person_aaaaaaaaaaaaaaaaaaaaaaaa.jpg':null, $id===15?0:1]);
    }
    $pdo->exec("INSERT INTO items VALUES(1,'Commemorative stamp','STAMP-001','2026-01-01',1),(2,'First day cover with a special cancellation','COVER-002','2026-02-01',1),(3,'Private acquisition record','PRIVATE-003','2026-03-01',0)");
    $pdo->exec("INSERT INTO media VALUES(1,1,'fixture-stamp.png','image'),(2,2,'fixture-cover.png','image')");
    $pdo->exec("INSERT INTO item_people VALUES(1,1,'Subject'),(1,1,'Associated'),(2,1,'Associated'),(3,1,'Private')");
    return $pdo;
}
