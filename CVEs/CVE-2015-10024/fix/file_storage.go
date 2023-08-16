package content

import (
	"errors"
	"io"
	"os"
	"path"
	"path/filepath"
	"strings"

	"github.com/hoffie/larasync/helpers/atomic"
)

const (
	// default permissions
	defaultFilePerms = 0600
	defaultDirPerms  = 0700
)

// ErrInvalidPath is returned if storage at a path not rooted at the FileStorage's
// root path is attempted.
var ErrInvalidPath = errors.New("invalid path")

// FileStorage is the basic implementation of the Storage
// implementation which stores the data into the file system.
type FileStorage struct {
	path string
}

// NewFileStorage generates a file content storage with the
// given path.
func NewFileStorage(path string) *FileStorage {
	return &FileStorage{
		path: path,
	}
}

// CreateDir ensures that the file blob storage directory exists.
func (f *FileStorage) CreateDir() error {
	err := os.Mkdir(f.path, defaultDirPerms)

	if err != nil && !os.IsExist(err) {
		return err
	}
	return nil
}

// storagePathFor returns the storage path for the data entry.
func (f *FileStorage) storagePathFor(contentID string) (string, error) {
	p := path.Join(f.path, contentID)
	p = filepath.Clean(p)
	root := f.path
	/*if len(root) > 1 && root[len(root)-1] != filepath.Separator {
		root += filepath.Separator
	}*/
	if !strings.HasPrefix(p, root) {
		return "", ErrInvalidPath
	}
	return p, nil
}

// Get returns the file handle for the given contentID.
// If there is no data stored for the Id it should return a
// os.ErrNotExists error.
func (f *FileStorage) Get(contentID string) (io.ReadCloser, error) {
	if !f.Exists(contentID) {
		return nil, os.ErrNotExist
	}
	// FIXME TOCTU race
	p, err := f.storagePathFor(contentID)
	if err != nil {
		return nil, err
	}
	return os.Open(p)
}

// Set sets the data of the given contentID in the blob storage.
func (f *FileStorage) Set(contentID string, reader io.Reader) error {
	blobStoragePath, err := f.storagePathFor(contentID)
	if err != nil {
		return err
	}

	writer, err := atomic.NewStandardWriter(blobStoragePath, defaultFilePerms)
	if err != nil {
		return err
	}

	_, err = io.Copy(writer, reader)
	if err != nil {
		writer.Abort()
		writer.Close()
		return err
	}

	err = writer.Close()
	if err != nil {
		return err
	}

	return nil
}

// Exists checks if the given entry is stored in the database.
func (f *FileStorage) Exists(contentID string) bool {
	p, err := f.storagePathFor(contentID)
	if err != nil {
		// FIXME maybe return error instead?
		return false
	}
	_, err = os.Stat(p)
	if err != nil {
		return !os.IsNotExist(err)
	}
	return true
}

// Delete removes the data with the given contentID from the store.
func (f *FileStorage) Delete(contentID string) error {
	p, err := f.storagePathFor(contentID)
	if err != nil {
		return err
	}
	return os.Remove(p)
}
