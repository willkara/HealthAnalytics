<?php include('SE1Exception.php');

class driver extends PDO
{
	function __construct(){
		try{
			parent::__construct('mysql:host=localhost;dbname=se1;charset=UTF8','se1','dfj59jnah19jmdig');
			$this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		}
		catch(PDOException $e){
			throw new Exception($e->getMessage());
		}
	}
}

class DatastoreException extends SE1Exception
{
    public function __construct($message, $code) {
        parent::__construct($message, $code);
    }
}

class datastore
{
	public $db;
	public $authenticatedUser=[];

	public function __construct(){
		try{
			$this->db=new driver();
		}
		catch(Exception $e){
			throw new DatastoreException('Unable to connect to database',2);
		}
	}

	private function __logError($error,$func){
		$pstmt=$this->db->prepare('INSERT INTO datastore_errors (error,func) VALUES (?,?)');
		$pstmt->execute([$error,$func]);
	}

	private function __authenticateUser($authtoken){
		try{
			$pstmt=$this->db->prepare('SELECT people.pkey,people.fname,people.lname,people.email FROM session INNER JOIN people ON people.pkey=session.person WHERE authtoken=?');
			$pstmt->execute([$authtoken]);
			if($pstmt->rowCount()>0){
				$rs=$pstmt->fetch(PDO::FETCH_ASSOC);
				$this->authenticatedUser=$rs;
			}
			else{throw new DatastoreException('User is not authenticated',3);}
		}
		catch(PDOException $e){throw new DatastoreException('ERROR, unable to authenication user',2);}
	}

	public function addUser($fname,$lname,$email,$passwd,$mi,$weight,$height,$birth_date,$gender,$waist_size,$address1,$address2,$city,$state,$zip){
		if(empty($fname)){throw new DatastoreException('You must provide the First Name',5);}
		if(empty($lname)){throw new DatastoreException('You must provide the Last Name',5);}
		if(empty($email)){throw new DatastoreException('You must provide the E-Mail Address',5);}
		if(empty($passwd)){throw new DatastoreException('You must provide the Password',5);}
		if(empty($mi)){throw new DatastoreException('You must provide the Middle Name Initial',5);}
		if(empty($weight)){throw new DatastoreException('You must provide the Weight',5);}
		if(empty($height)){throw new DatastoreException('You must provide the Height',5);}
		if(empty($birth_date)){throw new DatastoreException('You must provide the Birth Date',5);}
		if(empty($gender)){throw new DatastoreException('You must provide the Gender',5);}
		if(empty($waist_size)){throw new DatastoreException('You must provide the Waist Size',5);}
		if(empty($address1)){throw new DatastoreException('You must provide Address Line #1',5);}
		if(empty($city)){throw new DatastoreException('You must provide the City',5);}
		if(empty($state)){throw new DatastoreException('You must provide the State',5);}
		if(empty($zip)){throw new DatastoreException('You must provide the Zip Code',5);}
		if(!is_numeric($height)){throw new DatastoreException('Invalid Height',5);}
		if(!is_numeric($weight)){throw new DatastoreException('Invalid Weight',5);}
		if(!is_numeric($waist_size)){throw new DatastoreException('Invalid Waist Size',5);}
//$birth_date='2015-11-05T13:12:43.511Z';
//if (preg_match("/\d{4}-[0-1]\d-[0-3]\dT[0-2]\d:[0-5]\d:[0-5]\d:[0-5]\d\.?\d?\d?\d?Z/", $birth_date)) {
//    echo "A match was found.";
//} else {
//    echo "A match was not found.".$birth_date;
//}


		try{
			$pstmt=$this->db->prepare('INSERT INTO people (fname,lname,email,passwd,mi,weight,height,birth_date,gender,waist_size,address1,address2,city,state,zip) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
			$passwd=password_hash($passwd, PASSWORD_BCRYPT, ['cost'=>11]);
			$pstmt->execute([$fname,$lname,$email,$passwd,$mi,$weight,$height,$birth_date,strtoupper($gender),$waist_size,$address1,$address2,$city,$state,$zip]);
			return('{"results":"User successfully created"}');
		}
		catch(PDOException $e){
			$this->__logError($e->getMessage(),__FUNCTION__);
			if($e->errorInfo[1]==1062){$txt='This email address already exists';$code=4;}
			else{$txt='Unable to create user';$code=2;}
			throw new DatastoreException($txt,$code);
		}
	}

	function addWorkout($authtoken,$workout_type,$distance,$duration,$pace,$workout_timestamp,$calories){
		$this->__authenticateUser($authtoken);
		if(empty($workout_type)){throw new DatastoreException('You must provide the Workout Type',5);}
		if(empty($distance)){throw new DatastoreException('You must provide the Workout Distance',5);}
		if(empty($duration)){throw new DatastoreException('You must provide the Workout Duration',5);}
		if(empty($calories)){throw new DatastoreException('You must provide the Workout Calories',5);}
		if(empty($pace)){throw new DatastoreException('You must provide the Workout Pace',5);}
		if(empty($workout_timestamp)){throw new DatastoreException('You must provide the Workout Timestamp',5);}
		if(!is_numeric($distance)){throw new DatastoreException('Invalid Workout Distance',5);}
		if(!is_numeric($duration)){throw new DatastoreException('Invalid Workout Duration',5);}
		if(!is_numeric($calories)){throw new DatastoreException('Invalid Workout Calories',5);}
		if(!is_numeric($pace)){throw new DatastoreException('Invalid Workout Pace',5);}
		try{
			$pstmt=$this->db->prepare('INSERT INTO workout (workout_type,distance,duration,calories,pace,workout_timestamp,person) VALUES (?,?,?,?,?,?,?)');
			$pstmt->execute([$workout_type,$distance,$duration,$calories,$pace,$workout_timestamp,$this->authenticatedUser['pkey']]);
			return(json_encode(['workout_id'=>$this->db->lastInsertId()]));
		}
		catch(PDOException $e){throw new DatastoreException('Unable to save workout',1);}
	}

	public function getUser($email,$authtoken){
		$this->__authenticateUser($authtoken);
		try{
			$pstmt=$this->db->prepare('SELECT fname,mi,lname,role,email,weight,height,birth_date,gender,waist_size,address1,address2,city,state,zip FROM people WHERE email=?');
			$pstmt->execute([$email]);
			$rs=$pstmt->fetch(PDO::FETCH_ASSOC);
			if($rs['email']!=$this->authenticatedUser['email']){throw new DatastoreException('You cannot retrieve this user',2);}
			$rs['waist_size']=(int)$rs['waist_size'];
			$rs['height']=(int)$rs['height'];
			$rs['weight']=(int)$rs['weight'];
			return json_encode($rs);
		}
		catch(PDOException $e){throw new DatastoreException('Unable to retrieve user',1);}
	}


	public function loginUser($email,$password){
        $length=32;
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $authtoken='';
        while(strlen($authtoken)<$length){
            $authtoken.=$chars[mt_rand(0,strlen($chars))];
        }
		$pstmt=$this->db->prepare('SELECT pkey,passwd FROM people WHERE email=? LIMIT 1');
		try{
			$pstmt->execute([$email]);
			if($pstmt->rowCount()<1){
				throw new DatastoreException('E-Mail Address not found',1);
			}
			$rs=$pstmt->fetch(PDO::FETCH_ASSOC);
			if(!password_verify($password, $rs['passwd'])){
				throw new DatastoreException('ERROR, Invalid password',3);
			}
			$pstmt=$this->db->prepare('INSERT INTO session (authtoken,person) VALUES (?,?)');
			$pstmt->execute([$authtoken,$rs['pkey']]);
			return('{"authtoken":"'.$authtoken.'"}');
		}
		catch(PDOException $e){
			$this->__logError($e->getMessage(),__FUNCTION__);
			throw new DatastoreException('Unable to login',2);
		}
	}

	public function logoutUser($authtoken,$user){
		$this->__authenticateUser($authtoken);
		try{
			if($user!=$this->authenticatedUser['email']){throw new DatastoreException('You cannot logout this user',2);}
			$pstmt=$this->db->prepare('DELETE FROM session WHERE person=? AND authtoken=?');
			$pstmt->execute([$this->authenticatedUser['pkey'],$authtoken]);
			return('{"results":"Logout Complete"}');
		}
		catch(PDOException $e){throw new DatastoreException('Unable to logout user',1);}
	}

	function getAllWorkout($authtoken){
		$this->__authenticateUser($authtoken);
		try{
			$workout=[];
			$stmt=$this->db->query('SELECT workout.workout_id,workout_type,distance,workout_time,calories,ts,email FROM workout INNER JOIN people ON people.pkey=workout.`person`');
			if($stmt->rowCount()>0){
				while($rs=$stmt->fetch(PDO::FETCH_ASSOC)){
					$workout[]=$rs;
				}
			}
			return(json_encode($workout));
		}
		catch(PDOException $e){throw new DatastoreException('Unable to fetch all workouts',1);}
	}

	function getWorkout($authtoken,$workout_id){
		$this->__authenticateUser($authtoken);
		try{
			$pstmt=$this->db->prepare('SELECT workout.workout_id,workout_type,distance,workout_time,calories,ts,email FROM workout INNER JOIN people ON people.pkey=workout.`person` WHERE workout.workout_id=?');
			$pstmt->execute([$workout_id]);
			if($pstmt->rowCount()>0){
				$rs=$pstmt->fetch(PDO::FETCH_ASSOC);
				return(json_encode($rs));
			}
		}
		catch(PDOException $e){throw new DatastoreException('Unable to fetch workout',1);}
	}

}