package account

import (
	"errors"
	"math/rand"
	"strconv"
	"time"

	"github.com/kongchuanhujiao/server/internal/app/client"
	"github.com/kongchuanhujiao/server/internal/app/client/message"
	"github.com/kongchuanhujiao/server/internal/app/datahub/pkg/account"
	"github.com/kongchuanhujiao/server/internal/pkg/logger"

	"go.uber.org/zap"
)

// sendCode 发送验证码
func sendCode(id string) (err error) {

	a, err := account.SelectAccount(id, 0)
	if err != nil {
		logger.Error("发送验证码失败", zap.Error(err))
		return
	}

	if len(a) == 0 {
		return errors.New("账号不存在")
	}

	rand.Seed(time.Now().UnixNano())
	c := strconv.FormatFloat(rand.Float64(), 'f', -1, 64)[2:6]

	client.GetClient().SendMessage(
		message.NewTextMessage("您的验证码是：" + c + "，请勿泄露给他人。有效期5分钟").
			SetTarget(&message.Target{ID: a[0].QQ}),
	)

	account.WriteCode(id, c)

	go func() {
		timer := time.NewTimer(5 * time.Minute)
		defer timer.Stop()
		<-timer.C
		account.DeleteCode(id)
	}()

	return
}
