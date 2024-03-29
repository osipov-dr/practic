<?php

namespace stormProject;
include 'CsvErrClass.php';
include 'stormindex.php';
use \Exception;
use \PDO;

/*FILESIZE - максимальный размер файла в байтах
FIELDSNUMBER - кол-во полей, требуемых на ввод
FILETYPE - тип загружаемого файла*/
const FILESIZE = 2048;
const FIELDSNUMBER = 3;
const FILETYPE = 'application/vnd.ms-excel';

/*добавление найденной ошибки в список проблем*/
function addProblem($key, $errNum, array $problems)
{
    $wrongLine = $key + 1;
    $problems[] = new CsvErrClass($wrongLine, $errNum);
    return $problems;
}

/*обновление таблицы по данным из csv файла*/
function updateUniversity($connection, array $newUniversity)
{
    $sql = "SELECT id, parent_id, name FROM university";
    $result = $connection->prepare($sql);
    $result->execute();

    $oldUniversity = array();
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $oldUniversity[] = array(
            "id" => $row["id"],
            "parent_id" => $row["parent_id"],
            "name" => $row["name"]
        );
    }

    foreach ($newUniversity as $newKey => $newUni) {
        foreach ($oldUniversity as $oldkey => $oldUni) {
            if (($newUni[0] == $oldUni["id"]) && (!strcasecmp($newUni[2], $oldUni["name"]))) {
                unset ($newUniversity[$newKey]);
                unset ($oldUniversity[$oldkey]);
            }
        }
    }

    foreach ($oldUniversity as $oldkey => $oldUni) {
        $sql = "DELETE FROM university WHERE id = :unitId";
        $result = $connection->prepare($sql);
        $result->execute([
            "unitId" => (int)$oldUni["id"]
        ]);
    }

    foreach ($newUniversity as $newKey => $newUni) {
        $sql = "INSERT INTO university (`id`, `parent_id`, `name`) VALUES (:newID, :newParent, :newName)";
        $result = $connection->prepare($sql);
        $result->execute([
            "newID" => (int)$newUni[0],
            "newParent" => (int)$newUni[1],
            "newName" => $newUni[2],
        ]);
    }
}

/*проверка метода сервера*/
if ($_SERVER['REQUEST_METHOD'] !== "POST")
{
    throw new Exception("Wrong server method");
}

/*проверка на наличие ошибок и тип файла*/
if ($_FILES['inputfile']['error'] !== UPLOAD_ERR_OK || $_FILES['inputfile']['type'] !== FILETYPE) {
    throw new Exception("Wrong file type. File not uploaded");
}

/*проверка на наличие ошибок и тип файла*/
if ($_FILES['inputfile']['size'] > FILESIZE) {
    throw new Exception("File size exceed. File not uploaded");
}

$fileDir = $_FILES['inputfile']['tmp_name'];
$lines = array();
$key = 0;
$fp = fopen("$fileDir", "r");
while (($line = fgetcsv($fp, 100, ";", '"')) !== FALSE) {
    $key++;
    /*Проверка количества полей в строке*/
    if (count($line) !== FIELDSNUMBER) {
        throw new Exception("Wrong number of parameters on line $key");
    } else {
        foreach ($line as $key => $field) {
            $line[$key] = trim($field);
        }
        $lines[] = $line;
    }
}
fclose($fp);

/*проверка формы файла*/
$problems = array();
foreach ($lines as $key => $line) {
    /*проверка поля id*/
    if (!is_numeric($line[0])) {
        $problems = addProblem($key, 0, $problems);
    } elseif ((int)$line[0] <= 0) {
        $problems = addProblem($key, 1, $problems);
    }
    /*Проверка поля parent_id*/
    if (!is_numeric($line[1])) {
        $problems = addProblem($key, 2, $problems);
    } elseif ((int)$line[1] < 0) {
        $problems = addProblem($key, 3, $problems);
    }
    /*Проверка поля name*/
    if (strlen($line[2]) == 0) {
        $problems = addProblem($key, 4, $problems);
    } elseif (strlen($line[2]) > 255) {
        $problems = addProblem($key, 5, $problems);
    }
}

if (!empty($problems)) {
    echo json_encode($problems);
    return;
} else {
    $db = new DbClass();
    $db->connection->beginTransaction();
    try {
        updateUniversity($db->connection, $lines);
        $db->connection->commit();
    } catch (Exception $error) {
        $db->connection->rollBack();
        echo "Error: " . $error->getMessage();
        return;
    }
    echo json_encode('File Uploaded');
}







