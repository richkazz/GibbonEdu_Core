<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Comms\NotificationEvent;

include '../../functions.php';
include '../../config.php';

//New PDO DB connection
$pdo = new Gibbon\sqlConnection();
$connection2 = $pdo->getConnection();

@session_start();

//Module includes
include $_SESSION[$guid]['absolutePath'].'/modules/Activities/moduleFunctions.php';

$mode = $_POST['mode'];
$gibbonActivityID = $_POST['gibbonActivityID'];
$gibbonPersonID = $_POST['gibbonPersonID'];
$URL = $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/'.getModuleName($_POST['address'])."/activities_view_register.php&gibbonActivityID=$gibbonActivityID&gibbonPersonID=$gibbonPersonID&mode=$mode&search=".$_GET['search'];
$URLSuccess = $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/'.getModuleName($_POST['address'])."/activities_view.php&gibbonPersonID=$gibbonPersonID&search=".$_GET['search'];

if (isActionAccessible($guid, $connection2, '/modules/Activities/activities_view_register.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    $highestAction = getHighestGroupedAction($guid, '/modules/Activities/activities_view_register.php', $connection2);
    if ($highestAction == false) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
        exit;
    } else {
        //Get current role category
        $roleCategory = getRoleCategory($_SESSION[$guid]['gibbonRoleIDCurrent'], $connection2);

        //Check access controls
        $access = getSettingByScope($connection2, 'Activities', 'access');

        if ($access != 'Register') {
            //Fail0
            $URL .= '&return=error0';
            header("Location: {$URL}");
            exit;
        } else {
            //Proceed!
            //Check if school year specified
            if ($gibbonActivityID == '' or $gibbonPersonID == '') {
                $URL .= '&return=error1';
                header("Location: {$URL}");
                exit;
            } else {
                $today = date('Y-m-d');
                //Should we show date as term or date?
                $dateType = getSettingByScope($connection2, 'Activities', 'dateType');

                try {
                    if ($dateType != 'Date') {
                        $data = array('gibbonSchoolYearID' => $_SESSION[$guid]['gibbonSchoolYearID'], 'gibbonPersonID' => $gibbonPersonID, 'gibbonActivityID' => $gibbonActivityID);
                        $sql = "SELECT DISTINCT gibbonActivity.*, gibbonStudentEnrolment.gibbonYearGroupID, gibbonPerson.surname, gibbonPerson.preferredName, gibbonActivityType.access, gibbonActivityType.maxPerStudent, gibbonActivityType.enrolmentType, gibbonActivityType.waitingList, gibbonActivityType.backupChoice FROM gibbonActivity JOIN gibbonStudentEnrolment ON (gibbonActivity.gibbonYearGroupIDList LIKE concat( '%', gibbonStudentEnrolment.gibbonYearGroupID, '%' )) JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=gibbonStudentEnrolment.gibbonPersonID) LEFT JOIN gibbonActivityType ON (gibbonActivity.type=gibbonActivityType.name) WHERE gibbonActivity.gibbonSchoolYearID=:gibbonSchoolYearID AND gibbonStudentEnrolment.gibbonPersonID=:gibbonPersonID AND gibbonActivityID=:gibbonActivityID AND NOT gibbonSchoolYearTermIDList='' AND active='Y' AND registration='Y'";
                    } else {
                        $data = array('gibbonSchoolYearID' => $_SESSION[$guid]['gibbonSchoolYearID'], 'gibbonPersonID' => $gibbonPersonID, 'gibbonActivityID' => $gibbonActivityID, 'listingStart' => $today, 'listingEnd' => $today);
                        $sql = "SELECT DISTINCT gibbonActivity.*, gibbonStudentEnrolment.gibbonYearGroupID, gibbonPerson.surname, gibbonPerson.preferredName, gibbonActivityType.access, gibbonActivityType.maxPerStudent, gibbonActivityType.enrolmentType, gibbonActivityType.waitingList, gibbonActivityType.backupChoice FROM gibbonActivity JOIN gibbonStudentEnrolment ON (gibbonActivity.gibbonYearGroupIDList LIKE concat( '%', gibbonStudentEnrolment.gibbonYearGroupID, '%' )) JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=gibbonStudentEnrolment.gibbonPersonID) LEFT JOIN gibbonActivityType ON (gibbonActivity.type=gibbonActivityType.name) WHERE gibbonActivity.gibbonSchoolYearID=:gibbonSchoolYearID AND gibbonStudentEnrolment.gibbonPersonID=:gibbonPersonID AND gibbonActivityID=:gibbonActivityID AND listingStart<=:listingStart AND listingEnd>=:listingEnd AND active='Y' AND registration='Y'";
                    }
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit;
                }

                if ($result->rowCount() < 1) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit;
                } else {
                    $row = $result->fetch();

                    // Grab organizer info for notifications
                    try {
                        $dataStaff = array('gibbonActivityID' => $gibbonActivityID);
                        $sqlStaff = "SELECT gibbonPersonID FROM gibbonActivityStaff WHERE gibbonActivityID=:gibbonActivityID AND role='Organiser'";
                        $resultStaff = $connection2->prepare($sqlStaff);
                        $resultStaff->execute($dataStaff);
                    } catch (PDOException $e) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit;
                    }

                    $gibbonActivityStaffIDs = ($resultStaff->rowCount() > 0)? $resultStaff->fetchAll(\PDO::FETCH_COLUMN, 0) : array();

                    //Check for existing registration
                    try {
                        $dataReg = array('gibbonActivityID' => $gibbonActivityID, 'gibbonPersonID' => $gibbonPersonID);
                        $sqlReg = 'SELECT gibbonActivityStudentID, status FROM gibbonActivityStudent WHERE gibbonActivityID=:gibbonActivityID AND gibbonPersonID=:gibbonPersonID';
                        $resultReg = $connection2->prepare($sqlReg);
                        $resultReg->execute($dataReg);
                    } catch (PDOException $e) {
                        $URL .= '&return=error2';
                        header("Location: {$URL}");
                        exit;
                    }

                    if ($mode == 'register') {

                        if ($resultReg->rowCount() > 0) {
                            $URL .= '&return=error3';
                            header("Location: {$URL}");
                            exit;
                        } else {
                            // Load the backupChoice system setting, optionally override with the Activity Type setting
                            $backupChoice = getSettingByScope($connection2, 'Activities', 'backupChoice');
                            $backupChoice = (!empty($row['backupChoice']))? $row['backupChoice'] : $backupChoice;

                            $gibbonActivityIDBackup = ($backupChoice == 'Y')? $_POST['gibbonActivityIDBackup'] : '';
                            $activityCountByType = getStudentActivityCountByType($pdo, $row['type'], $gibbonPersonID);
                            
                            if (!empty($row['access']) && $row['access'] != 'Register') {
                                $URL .= '&return=error0';
                                header("Location: {$URL}");
                                exit;
                            } else if ($row['maxPerStudent'] > 0 && $activityCountByType >= $row['maxPerStudent']) {
                                $URL .= '&return=error1';
                                header("Location: {$URL}");
                                exit;
                            } else if ($backupChoice == 'Y' and $gibbonActivityIDBackup == '') {
                                $URL .= '&return=error1';
                                header("Location: {$URL}");
                                exit;
                            } else {
                                $status = 'Not Accepted';

                                // Load the enrolmentType system setting, optionally override with the Activity Type setting
                                $enrolment = getSettingByScope($connection2, 'Activities', 'enrolmentType');
                                $enrolment = (!empty($row['enrolmentType']))? $row['enrolmentType'] : $enrolment;

                                //Lock the activityStudent database table
                                try {
                                    $sql = 'LOCK TABLES gibbonActivityStudent WRITE, gibbonPerson WRITE';
                                    $result = $connection2->query($sql);
                                } catch (PDOException $e) {
                                    $URL .= '&return=error2';
                                    header("Location: {$URL}");
                                    exit;
                                }

                                if ($enrolment == 'Selection') {
                                    $status = 'Pending';
                                } else {
                                    //Check number of people registered for this activity (if we ignore status it stops people jumping the queue when someone unregisters)
                                    try {
                                        $dataNumberRegistered = array('gibbonActivityID' => $gibbonActivityID, 'date' => date('Y-m-d'));
                                        $sqlNumberRegistered = "SELECT * FROM gibbonActivityStudent JOIN gibbonPerson ON (gibbonActivityStudent.gibbonPersonID=gibbonPerson.gibbonPersonID) WHERE gibbonPerson.status='Full' AND (dateEnd IS NULL  OR dateEnd>=:date) AND gibbonActivityID=:gibbonActivityID";
                                        $resultNumberRegistered = $connection2->prepare($sqlNumberRegistered);
                                        $resultNumberRegistered->execute($dataNumberRegistered);
                                    } catch (PDOException $e) {
                                        echo "<div class='error'>".$e->getMessage().'</div>';
                                    }

                                    //If activity is full...
                                    if ($resultNumberRegistered->rowCount() >= $row['maxParticipants']) {
                                        if ($row['waitingList'] == 'Y') {
                                            $status = 'Waiting List';
                                        } else {
                                            $URL .= '&return=error1';
                                            header("Location: {$URL}");
                                            exit;
                                        }
                                    } else {
                                        $status = 'Accepted';
                                    }
                                }

                                //Write to database
                                try {
                                    $data = array('gibbonActivityID' => $gibbonActivityID, 'gibbonPersonID' => $gibbonPersonID, 'status' => $status, 'timestamp' => date('Y-m-d H:i:s', time()), 'gibbonActivityIDBackup' => $gibbonActivityIDBackup);
                                    $sql = 'INSERT INTO gibbonActivityStudent SET gibbonActivityID=:gibbonActivityID, gibbonPersonID=:gibbonPersonID, status=:status, timestamp=:timestamp, gibbonActivityIDBackup=:gibbonActivityIDBackup';
                                    $result = $connection2->prepare($sql);
                                    $result->execute($data);
                                } catch (PDOException $e) {
                                    $URL .= '&return=error2';
                                    header("Location: {$URL}");
                                    exit;
                                }

                                //Unlock locked database tables
                                try {
                                    $sql = 'UNLOCK TABLES';
                                    $result = $connection2->query($sql);
                                } catch (PDOException $e) {
                                }

                                // Get the start and end date of the activity, depending on which dateType we're using
                                $activityTimespan = getActivityTimespan($connection2, $gibbonActivityID, $row['gibbonSchoolYearTermIDList']);

                                // Is the activity running right now?
                                if (time() >= $activityTimespan['start'] && time() <= $activityTimespan['end']) {
                                    // Raise a new notification event
                                    $event = new NotificationEvent('Activities', 'New Activity Registration');

                                    $studentName = formatName('', $row['preferredName'], $row['surname'], 'Student', false);
                                    $notificationText = sprintf(__('%1$s has registered for the activity %2$s (%3$s)'), $studentName, $row['name'], $status);

                                    $event->setNotificationText($notificationText);
                                    $event->setActionLink('/index.php?q=/modules/Activities/activities_manage_enrolment.php&gibbonActivityID='.$gibbonActivityID.'&search=&gibbonSchoolYearTermID=');

                                    $event->addScope('gibbonPersonIDStudent', $gibbonPersonID);
                                    $event->addScope('gibbonYearGroupID', $row['gibbonYearGroupID']);

                                    foreach ($gibbonActivityStaffIDs as $gibbonPersonIDStaff) {
                                        $event->addRecipient($gibbonPersonIDStaff);
                                    }

                                    $event->sendNotifications($pdo, $gibbon->session);
                                }

                                if ($status == 'Waiting List') {
                                    $URLSuccess = $URLSuccess.'&return=success2';
                                    header("Location: {$URLSuccess}");
                                    exit;
                                } else {
                                    $URLSuccess = $URLSuccess.'&return=success0';
                                    header("Location: {$URLSuccess}");
                                    exit;
                                }
                            }
                        }
                    } elseif ($mode == 'unregister') {

                        if ($resultReg->rowCount() < 1) {
                            $URL .= '&return=error3';
                            header("Location: {$URL}");
                            exit;
                        } else {
                            if (!empty($row['access']) && $row['access'] != 'Register') {
                                $URL .= '&return=error0';
                                header("Location: {$URL}");
                                exit;
                            }

                            //Write to database
                            try {
                                $data = array('gibbonActivityID' => $gibbonActivityID, 'gibbonPersonID' => $gibbonPersonID);
                                $sql = 'DELETE FROM gibbonActivityStudent WHERE gibbonActivityID=:gibbonActivityID AND gibbonPersonID=:gibbonPersonID';
                                $result = $connection2->prepare($sql);
                                $result->execute($data);
                            } catch (PDOException $e) {
                                $URL .= '&return=error2';
                                header("Location: {$URL}");
                                exit;
                            }

                            $reg = $resultReg->fetch();

                            // Raise a new notification event
                            if ($reg['status'] == 'Accepted') {
                                // Get the start and end date of the activity, depending on which dateType we're using
                                $activityTimespan = getActivityTimespan($connection2, $gibbonActivityID, $row['gibbonSchoolYearTermIDList']);

                                // Is the activity running right now?
                                if (time() >= $activityTimespan['start'] && time() <= $activityTimespan['end']) {
                                    $event = new NotificationEvent('Activities', 'Student Withdrawn');

                                    $studentName = formatName('', $row['preferredName'], $row['surname'], 'Student', false);
                                    $notificationText = sprintf(__('%1$s has withdrawn from the activity %2$s'), $studentName, $row['name']);

                                    $event->setNotificationText($notificationText);
                                    $event->setActionLink('/index.php?q=/modules/Activities/activities_manage_enrolment.php&gibbonActivityID='.$gibbonActivityID.'&search=&gibbonSchoolYearTermID=');

                                    $event->addScope('gibbonPersonIDStudent', $gibbonPersonID);
                                    $event->addScope('gibbonYearGroupID', $row['gibbonYearGroupID']);

                                    foreach ($gibbonActivityStaffIDs as $gibbonPersonIDStaff) {
                                        $event->addRecipient($gibbonPersonIDStaff);
                                    }

                                    $event->sendNotifications($pdo, $gibbon->session);
                                }
                            }

                            //Bump up any waiting in competitive selection, to fill spaces available
                            $enrolment = getSettingByScope($connection2, 'Activities', 'enrolmentType');
                            if ($enrolment == 'Competitive') {
                                //Lock the activityStudent database table
                                try {
                                    $sql = 'LOCK TABLES gibbonActivityStudent WRITE, gibbonPerson WRITE';
                                    $result = $connection2->query($sql);
                                } catch (PDOException $e) {
                                    $URL .= '&return=error2';
                                    header("Location: {$URL}");
                                    exit;
                                }

                                //Count spaces
                                try {
                                    $dataNumberRegistered = array('gibbonActivityID' => $gibbonActivityID);
                                    $sqlNumberRegistered = "SELECT * FROM gibbonActivityStudent JOIN gibbonPerson ON (gibbonActivityStudent.gibbonPersonID=gibbonPerson.gibbonPersonID) WHERE gibbonPerson.status='Full' AND (dateEnd IS NULL  OR dateEnd>='".date('Y-m-d')."') AND gibbonActivityID=:gibbonActivityID AND gibbonActivityStudent.status='Accepted'";
                                    $resultNumberRegistered = $connection2->prepare($sqlNumberRegistered);
                                    $resultNumberRegistered->execute($dataNumberRegistered);
                                } catch (PDOException $e) {
                                }

                                //If activity is not full...
                                $spaces = $row['maxParticipants'] - $resultNumberRegistered->rowCount();
                                if ($spaces > 0) {
                                    //Get top of waiting list
                                    try {
                                        $dataBumps = array('gibbonActivityID' => $gibbonActivityID);
                                        $sqlBumps = "SELECT * FROM gibbonActivityStudent JOIN gibbonPerson ON (gibbonActivityStudent.gibbonPersonID=gibbonPerson.gibbonPersonID) WHERE gibbonPerson.status='Full' AND (dateEnd IS NULL  OR dateEnd>='".date('Y-m-d')."') AND gibbonActivityID=:gibbonActivityID AND gibbonActivityStudent.status='Waiting List' ORDER BY timestamp ASC LIMIT 0, $spaces";
                                        $resultBumps = $connection2->prepare($sqlBumps);
                                        $resultBumps->execute($dataBumps);
                                    } catch (PDOException $e) {
                                    }

                                    //Bump students up
                                    while ($rowBumps = $resultBumps->fetch()) {
                                        try {
                                            $dataBump = array('gibbonActivityStudentID' => $rowBumps['gibbonActivityStudentID']);
                                            $sqlBump = "UPDATE gibbonActivityStudent SET status='Accepted' WHERE gibbonActivityStudentID=:gibbonActivityStudentID";
                                            $resultBump = $connection2->prepare($sqlBump);
                                            $resultBump->execute($dataBump);
                                        } catch (PDOException $e) {
                                        }
                                    }
                                }
                                //Unlock locked database tables
                                try {
                                    $sql = 'UNLOCK TABLES';
                                    $result = $connection2->query($sql);
                                } catch (PDOException $e) {
                                }
                            }

                            $URLSuccess = $URLSuccess.'&return=success1';
                            header("Location: {$URLSuccess}");
                        }
                    }
                }
            }
        }
    }
}
