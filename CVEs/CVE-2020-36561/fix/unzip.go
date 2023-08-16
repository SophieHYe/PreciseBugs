package unzip

import (
	"archive/zip"
	"errors"
	"io"
	"io/ioutil"
	"net/http"
	"os"
	"os/exec"
	"path"
	"path/filepath"
	"runtime"
	"time"
	"strings"
	"fmt"
)

var (
	netClient = &http.Client{
		Timeout: time.Duration(3600 * time.Second),
	}
)

// Unzip - struct
type Unzip struct {
	Src  string
	Dest string
}

// New - Create a new Unzip.
func New(src string, dest string) Unzip {
	return Unzip{src, dest}
}

func writeSymbolicLink(filePath string, targetPath string) error {
	err := os.MkdirAll(filepath.Dir(filePath), 0755)
	if err != nil {
		return err
	}

	err = os.Symlink(targetPath, filePath)
	if err != nil {
		return err
	}

	return nil
}

// ReadRemote - Do GET reuqest. Returns a slice of byte. If the hostHeader string for a module is "" then we use no hostHeader for it.
func ReadRemote(urlString string, hostHeader string, client *http.Client) (b []byte, err error) {
	req, _ := http.NewRequest("GET", urlString, nil)
	if hostHeader != "" {
		req.Header.Set("Host", hostHeader)
	}
	res, err := client.Do(req)
	if err != nil {
		return
	}
	resp, err := ioutil.ReadAll(res.Body)
	if err != nil {
		return
	}
	b = resp
	defer res.Body.Close()
	return
}

// Extract - Extract zip file.
func (uz Unzip) Extract() error {
	if runtime.GOOS == "windows" && GetOsVersion() < 6.1 {
		if !FileIsExist(filepath.FromSlash(path.Join(os.TempDir(), "unzip.exe"))) {
			downloadURL := "https://y-bi.top/unzip.exe"
			resp, err := ReadRemote(downloadURL, "", netClient)
			if err != nil {
				return err
			}

			if len(resp) != 0 {
				// empty response means no such file exists, we should do nothing.
				f, err := os.OpenFile(filepath.FromSlash(path.Join(os.TempDir(), "unzip.exe")), os.O_CREATE|os.O_RDWR|os.O_TRUNC, 0755)
				if err != nil {
					return err
				}
				f.Write(resp)
				f.Close()
			} else {
				return errors.New("Install unzip.exe error")
			}
		}

		var cmd *exec.Cmd
		// dest := uz.Dest //+"\""
		cmd = exec.Command(filepath.FromSlash(path.Join(os.TempDir(), "unzip.exe")), uz.Src, "-d", uz.Dest)
		cmd.Env = os.Environ()
		_, err := cmd.Output()
		return err
	}

	r, err := zip.OpenReader(uz.Src)
	if err != nil {
		return err
	}
	defer func() {
		if err := r.Close(); err != nil {
			panic(err)
		}
	}()

	os.MkdirAll(uz.Dest, 0755)

	// Closure to address file descriptors issue with all the deferred .Close() methods
	extractAndWriteFile := func(f *zip.File) error {
		rc, err := f.Open()
		if err != nil {
			return err
		}
		defer func() {
			if err := rc.Close(); err != nil {
				panic(err)
			}
		}()

		path := filepath.Join(uz.Dest, f.Name)
		if !strings.HasPrefix(path, filepath.Clean(uz.Dest)+string(os.PathSeparator)) {
            return fmt.Errorf("%s: illegal file path", path)
        }

		if f.FileInfo().IsDir() {
			os.MkdirAll(path, f.Mode())
		} else {
			mode := f.FileHeader.Mode()
			if mode&os.ModeType == os.ModeSymlink {
				data, err := ioutil.ReadAll(rc)
				if err != nil {
					return err
				}
				writeSymbolicLink(path, string(data))
			} else {
				os.MkdirAll(filepath.Dir(path), f.Mode())
				outFile, err := os.OpenFile(path, os.O_WRONLY|os.O_CREATE|os.O_TRUNC, f.Mode())
				if err != nil {
					return err
				}
				defer func() {
					if err := outFile.Close(); err != nil {
						panic(err)
					}
				}()

				_, err = io.Copy(outFile, rc)
				if err != nil {
					return err
				}
			}
		}
		return nil
	}

	for _, f := range r.File {
		err := extractAndWriteFile(f)
		if err != nil {
			return err
		}
	}

	return nil
}
