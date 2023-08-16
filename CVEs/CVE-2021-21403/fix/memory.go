package account

import (
	"math/rand"
	"strconv"
	"time"

	"github.com/kongchuanhujiao/server/internal/app/datahub/internal/memory"
)

// GenerateCode 写入验证码返回一个验证码
func GenerateCode(id string) (c string) {

	rand.Seed(time.Now().UnixNano())
	c = strconv.FormatFloat(rand.Float64(), 'f', -1, 64)[2:6]

	memory.Code[id] = c

	go func() {
		timer := time.NewTimer(5 * time.Minute)
		defer timer.Stop()
		<-timer.C
		deleteCode(id)
	}()

	return
}

// VerifyCode 验证验证码
func VerifyCode(id string, code string) (ok bool) {
	if code == memory.Code[id] {
		ok = true
		deleteCode(id)
	}
	return
}

// deleteCode 删除验证码
func deleteCode(id string) { delete(memory.Code, id) }
