<?php

/**
 * KaikMedia AuthModule
 *
 * @package    KaikmediaAuthModule
 * @author     Kaik <contact@kaikmedia.com>
 * @copyright  KaikMedia
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @link       https://github.com/Kaik/KaikmediaAuthModule.git
 */

namespace Kaikmedia\AuthModule\Helper;

use Imagine\Gd\Imagine;
use Imagine\Image\Box;

class AvatarHelper
{
    /**
     * List of allowed file extensions
     * @var array
     */
    private $imageExtensions;

    /**
     * @var array
     */
    private $modVars;

    /**
     * @var string
     */
    private $avatarPath;

    /**
     * UploadHelper constructor.
     *
     * @param array $modVars
     * @param string $avatarPath
     */
    public function __construct(
        $modVars = [],
        $avatarPath = ''
    ) {
        $this->imageExtensions = ['gif', 'jpeg', 'jpg', 'png'/*, 'swf'*/];
        $this->modVars = $modVars;
        $this->avatarPath = $avatarPath;
    }

    /**
     * Process a given url file.
     *
     * @param $file
     * @param int $userId
     * @return The resulting file name
     */
    public function handleDownload($file, $userId = 0)
    {
        $allowUploads = isset($this->modVars['allowUploads']) && true === boolval($this->modVars['allowUploads']);
        if (!$allowUploads) {
            throw new \InvalidArgumentException('Uploads are not allowed');
        }
        if (!file_exists($this->avatarPath) || !is_readable($this->avatarPath) || !is_writable($this->avatarPath)) {
            throw new \InvalidArgumentException('Avatar path is unreachable');
        }

        if (!is_numeric($userId) || $userId < 1) {
            throw new \InvalidArgumentException('User id is invalid');
        }

        $tmp_name = 'pers_tmp' . $userId;
        $filePath = $this->avatarPath . '/' . $tmp_name;
        if(!@copy($file, $filePath)) {
            $errors = error_get_last();
            throw new \InvalidArgumentException($errors);
        }

        // check for file size limit
        if (!$this->modVars['shrinkLargeImages'] && filesize($filePath) > $this->modVars['maxSize']) {
            unlink($filePath);

            throw new \InvalidArgumentException('File size is too big.');
        }

        // Get image information
        $imageInfo = getimagesize($filePath);
        if (!$imageInfo) {
            // file is not an image
            unlink($filePath);

            throw new \InvalidArgumentException('Unknow file.');
        }

        $extension = image_type_to_extension($imageInfo[2], false);
        // check for image type
        if (!in_array($extension, $this->imageExtensions)) {
            unlink($filePath);

            throw new \InvalidArgumentException('Unsuported file format.');
        }

        // check for image dimensions limit
        $isTooLarge = $imageInfo[0] > $this->modVars['maxWidth'] || $imageInfo[1] > $this->modVars['maxHeight'];

        if ($isTooLarge && !$this->modVars['shrinkLargeImages']) {
            unlink($filePath);

            throw new \InvalidArgumentException('File size is too big.');
        }

        // everything's OK, so move the file
        $avatarFileNameWithoutExtension = 'pers_' . $userId;
        $avatarFileName = $avatarFileNameWithoutExtension . '.' . $extension;
        $avatarFilePath = $this->avatarPath . '/' . $avatarFileName;

        // delete old user avatar
        foreach ($this->imageExtensions as $ext) {
            $oldFilePath = $this->avatarPath . '/' . $avatarFileNameWithoutExtension . '.' . $ext;
            if (file_exists($oldFilePath)) {
                unlink($oldFilePath);
            }
        }

        if(!@copy($filePath, $avatarFilePath)) {
            $errors = error_get_last();
            unlink($filePath);
            throw new \InvalidArgumentException($errors);
        }

        unlink($filePath);

        if ($isTooLarge && $this->modVars['shrinkLargeImages']) {
            // resize the image
            $imagine = new Imagine();
            $image = $imagine->open($avatarFilePath);
            $image->resize(new Box($this->modVars['maxWidth'], $this->modVars['maxHeight']))
                  ->save($avatarFilePath);
        }

        chmod($avatarFilePath, 0644);

        return $avatarFileName;
    }

    public function getAvatarSrc(String $avatarFileName = null)
    {
        if (!$avatarFileName) {
            throw new \InvalidArgumentException('Missing avatar file name.');
        }

        return $this->avatarPath . '/' . $avatarFileName;
    }
}
