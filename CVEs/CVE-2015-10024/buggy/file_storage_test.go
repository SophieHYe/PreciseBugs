package content

import (
	"bytes"
	"crypto/sha256"
	"encoding/hex"
	"io"
	"io/ioutil"
	"os"
	"path"

	. "gopkg.in/check.v1"
)

type FileStorageTests struct {
	dir     string
	storage *FileStorage
	data    []byte
}

var _ = Suite(&FileStorageTests{})

func (t *FileStorageTests) SetUpTest(c *C) {
	t.dir = c.MkDir()
	t.storage = NewFileStorage(t.dir)
	t.data = []byte("This is a test blob storage file input.")
}

func (t *FileStorageTests) blobID() string {
	blobIDBytes := sha256.New().Sum(t.data)
	return hex.EncodeToString(blobIDBytes[:])
}

func (t *FileStorageTests) blobPath() string {
	return path.Join(t.dir, t.blobID())
}

func (t *FileStorageTests) testReader() io.Reader {
	return bytes.NewReader(t.data)
}

func (t *FileStorageTests) setData() error {
	return t.storage.Set(t.blobID(), t.testReader())
}

func (t *FileStorageTests) TestSet(c *C) {
	err := t.setData()
	c.Assert(err, IsNil)
	_, err = os.Stat(t.blobPath())
	c.Assert(err, IsNil)
}

func (t *FileStorageTests) TestSetInputData(c *C) {
	t.setData()
	file, _ := os.Open(t.blobPath())
	fileData, _ := ioutil.ReadAll(file)
	c.Assert(fileData[:], DeepEquals, t.data[:])
}

func (t *FileStorageTests) TestExistsNegative(c *C) {
	c.Assert(t.storage.Exists(t.blobID()), Equals, false)
}

func (t *FileStorageTests) TestExistsPositive(c *C) {
	t.setData()
	c.Assert(t.storage.Exists(t.blobID()), Equals, true)
}

func (t *FileStorageTests) TestGet(c *C) {
	t.storage.Set(t.blobID(), t.testReader())
	_, err := t.storage.Get(t.blobID())
	c.Assert(err, IsNil)
}

func (t *FileStorageTests) TestGetData(c *C) {
	t.setData()
	file, _ := t.storage.Get(t.blobID())
	fileData, _ := ioutil.ReadAll(file)
	c.Assert(fileData[:], DeepEquals, t.data)
}

func (t *FileStorageTests) TestGetError(c *C) {
	_, err := t.storage.Get(t.blobID())
	c.Assert(err, NotNil)
}

func (t *FileStorageTests) TestSetError(c *C) {
	os.RemoveAll(t.dir)

	err := t.storage.Set(t.blobID(),
		t.testReader())
	c.Assert(err, NotNil)
}

func (t *FileStorageTests) TestDelete(c *C) {
	t.setData()
	err := t.storage.Delete(t.blobID())
	c.Assert(err, IsNil)
	c.Assert(t.storage.Exists(t.blobID()), Equals, false)
}

func (t *FileStorageTests) TestDeleteError(c *C) {
	err := t.storage.Delete(t.blobID())
	c.Assert(err, NotNil)
}
