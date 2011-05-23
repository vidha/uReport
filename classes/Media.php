<?php
/**
 * @copyright 2006-2011 City of Bloomington, Indiana
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.txt
 * @author Cliff Ingham <inghamn@bloomington.in.gov>
 */
class Media extends MongoRecord
{
	public static $extensions = array(
		'jpg' =>array('mime_type'=>'image/jpeg','media_type'=>'image'),
		'gif' =>array('mime_type'=>'image/gif','media_type'=>'image'),
		'png' =>array('mime_type'=>'image/png','media_type'=>'image'),
		'tiff'=>array('mime_type'=>'image/tiff','media_type'=>'image'),
		'pdf' =>array('mime_type'=>'application/pdf','media_type'=>'attachment'),
		'rtf' =>array('mime_type'=>'application/rtf','media_type'=>'attachment'),
		'doc' =>array('mime_type'=>'application/msword','media_type'=>'attachment'),
		'xls' =>array('mime_type'=>'application/msexcel','media_type'=>'attachment'),
		'gz'  =>array('mime_type'=>'application/x-gzip','media_type'=>'attachment'),
		'zip' =>array('mime_type'=>'application/zip','media_type'=>'attachment'),
		'txt' =>array('mime_type'=>'text/plain','media_type'=>'attachment'),
		'wmv' =>array('mime_type'=>'video/x-ms-wmv','media_type'=>'video'),
		'mov' =>array('mime_type'=>'video/quicktime','media_type'=>'video'),
		'rm'  =>array('mime_type'=>'application/vnd.rn-realmedia','media_type'=>'video'),
		'ram' =>array('mime_type'=>'audio/vnd.rn-realaudio','media_type'=>'audio'),
		'mp3' =>array('mime_type'=>'audio/mpeg','media_type'=>'audio'),
		'mp4' =>array('mime_type'=>'video/mp4','media_type'=>'video'),
		'flv' =>array('mime_type'=>'video/x-flv','media_type'=>'video'),
		'wma' =>array('mime_type'=>'audio/x-ms-wma','media_type'=>'audio'),
		'kml' =>array('mime_type'=>'application/vnd.google-earth.kml+xml','media_type'=>'attachment'),
		'swf' =>array('mime_type'=>'application/x-shockwave-flash','media_type'=>'attachment'),
		'eps' =>array('mime_type'=>'application/postscript','media_type'=>'attachment')
	);

	/**
	 * Populates the object with data
	 *
	 * Passing in an associative array of data will populate this object without
	 * hitting the database.
	 *
	 * @param array $data
	 */
	public function __construct($data)
	{
		if (is_array($data)) {
			$this->data = $data;
		}
		else {
			// This is where the code goes to generate a new, empty instance.
			// Set any default values for properties that need it here
			$this->uploaded = new MongoDate();
			$this->setPerson($_SESSION['USER']);
		}
	}

	/**
	 * Throws an exception if anything's wrong
	 * @throws Exception $e
	 */
	public function validate()
	{
		// Check for required fields here.  Throw an exception if anything is missing.
		if (!$this->data['filename'] || !$this->data['mime_type'] || !$this->data['media_type']) {
			throw new Exception('missingRequiredFields');
		}
	}

	public function delete()
	{
		// Delete the file from the hard drive
		unlink($this->getDirectory().'/'.$this->getInternalFilename());

		if ($this->id) {
			// Clear out the database
			$zend_db = Database::getConnection();
			$zend_db->delete('issue_media','media_id='.$this->id);
			$zend_db->delete('media','id='.$this->id);
		}
	}
	//----------------------------------------------------------------
	// Generic Getters
	//----------------------------------------------------------------
	/**
	 * @return string
	 */
	public function getFilename()
	{
		return $this->data['filename'];
	}

	/**
	 * @return string
	 */
	public function getMime_type()
	{
		return $this->data['mime_type'];
	}

	/**
	 * @return string
	 */
	public function getMedia_type()
	{
		return $this->data['media_type'];
	}

	/**
	 * Returns the date/time in the desired format
	 *
	 * Format is specified using PHP's date() syntax
	 * http://www.php.net/manual/en/function.date.php
	 * If no format is given, the Date object is returned
	 *
	 * @param string $format
	 * @return string|DateTime
	 */
	public function getUploaded($format=null)
	{
		if ($format) {
			list($microseconds,$timestamp) = explode(' ',$this->data['uploaded']);
			return date($format,$timestamp);
		}
		else {
			return $this->data['uploaded'];
		}
	}

	/**
	 * @return array
	 */
	public function getPerson()
	{
		return $this->data['person'];
	}

	//----------------------------------------------------------------
	// Generic Setters
	//----------------------------------------------------------------
	/**
	 * @param string|Person $person
	 */
	public function setPerson($person)
	{
		if (!$person instanceof Person) {
			$person = new Person($person);
		}
		$this->data['person'] = array(
			'_id'=>$person->getId(),
			'fullname'=>$person->getFullname()
		);
	}

	//----------------------------------------------------------------
	// Custom Functions
	// We recommend adding all your custom code down here at the bottom
	//----------------------------------------------------------------
	/**
	 * Alias for getMedia_type()
	 */
	public function getType()
	{
		return $this->getMedia_type();
	}

	/**
	 * Alias for Upload date
	 *
	 * Media doesn't get modified, it just gets re-uploaded
	 */
	public function getModified($format)
	{
		return $this->getUploaded($format);
	}

	/**
	 * Populates this object by reading information on a file
	 *
	 * This function does the bulk of the work for setting all the required information.
	 * It tries to read as much meta-data about the file as possible
	 *
	 * @param array|string Either a $_FILES array or a path to a file
	 */
	public function setFile($file)
	{
		# Handle passing in either a $_FILES array or just a path to a file
		$tempFile = is_array($file) ? $file['tmp_name'] : $file;
		$filename = is_array($file) ? basename($file['name']) : basename($file);
		if (!$tempFile) {
			throw new Exception('media/uploadFailed');
		}

		// Clean all bad characters from the filename
		$filename = $this->createValidFilename($filename);
		$this->data['filename'] = $filename;
		$extension = $this->getExtension();

		// Find out the mime type for this file
		if (array_key_exists(strtolower($extension),Media::$extensions)) {
			$this->data['mime_type'] = Media::$extensions[$extension]['mime_type'];
			$this->data['media_type'] = Media::$extensions[$extension]['media_type'];
		}
		else {
			throw new Exception('unknownFileType');
		}

		// Clean out any previous version of the file
		if ($this->id) {
			foreach(glob("{$this->getDirectory()}/{$this->id}.*") as $file) {
				unlink($file);
			}
		}

		// Move the file where it's supposed to go
		if (!is_dir($this->getDirectory())) {
			mkdir($this->getDirectory(),0777,true);
		}
		$newFile = $this->getDirectory().'/'.$this->getInternalFilename();
		rename($tempFile,$newFile);
		chmod($newFile,0666);

		if (!is_file($this->getDirectory().'/'.$this->getInternalFilename())) {
			throw new Exception('media/uploadFailed');
			exit();
		}
	}

	/**
	 * Returns the path to where the file should be stored
	 *
	 * Media is stored in the data directory, outside of the web directory
	 *
	 * @return string
	 */
	public function getDirectory()
	{
		$year = $this->uploaded->format('Y');
		$month = $this->uploaded->format('m');
		$day = $this->uploaded->format('d');
		return APPLICATION_HOME."/data/media/$year/$month/$day";
	}

	/**
	 * Returns the URL to this media
	 *
	 * @return string
	 */
	public function getURL()
	{
		$year = $this->uploaded->format('Y');
		$month = $this->uploaded->format('m');
		$day = $this->uploaded->format('d');
		$url = BASE_URL."/media/media/$year/$month/$day";

		$filename = $this->getInternalFilename();

		$url.="/$filename";

		return $url;
	}

	/**
	 * Returns the system-generated filename
	 *
	 * We don't use the user-provided filenames when saving the file to the server's hard drive
	 * Instead, we use the ID of this media object as the filename
	 *
	 * @return string
	 */
	public function getInternalFilename()
	{
		// We've got a chicken-or-egg problem here.  We want to use the id
		// as the filename, but the id doesn't exist until the info's been saved
		// to the database.
		//
		// If we don't have an id yet, try and save to the database first.
		// If that fails, we most likely don't have enough required info yet
		if (!$this->id) {
			$this->save();
		}
		return "{$this->id}.{$this->getExtension()}";
	}

	/**
	 * @return string
	 */
	public function getExtension()
	{
		preg_match("/[^.]+$/",$this->filename,$matches);
		return strtolower($matches[0]);
	}

	/**
	 * @return int
	 */
	public function getFilesize()
	{
		return filesize($this->getDirectory().'/'.$this->getInternalFilename());
	}


	/**
	 * Cleans a filename of any characters that might cause problems on filesystems
	 *
	 * @return string
	 */
	public static function createValidFilename($string)
	{
		// No bad characters
		$string = preg_replace('/[^A-Za-z0-9_\.\s]/','',$string);

		// Convert spaces to underscores
		$string = preg_replace('/\s+/','_',$string);

		// Lower case any file extension
		if (preg_match('/(^.*\.)([^\.]+)$/',$string,$matches)) {
			$string = $matches[1].strtolower($matches[2]);
		}

		return $string;
	}

	/**
	 * @return Issue
	 */
	public function getIssue()
	{
		if ($this->id) {
			$zend_db = Database::getConnection();

			$issue_id = $zend_db->fetchOne(
				'select issue_id from issue_media where media_id=?',
				array($this->id)
			);
			return new Issue($issue_id);
		}
	}
}