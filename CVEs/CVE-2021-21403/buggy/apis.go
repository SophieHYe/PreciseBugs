package account

import (
	"github.com/kongchuanhujiao/server/internal/app/datahub/pkg/account"
	"time"

	"github.com/kongchuanhujiao/server/internal/app/kongchuanhujiao"
	"github.com/kongchuanhujiao/server/internal/pkg/config"
	"github.com/kongchuanhujiao/server/internal/pkg/logger"

	"github.com/iris-contrib/middleware/jwt"
	"go.uber.org/zap"
)

// APIs 账号 APIs
type APIs struct{}

// PostCodeReq 发送验证码 请求结构
type PostCodeReq struct {
	ID string // 标识号
}

// PostCode 发送验证码 APIs。
// 调用方法：POST apis/accounts/code
func (a *APIs) PostCode(v *PostCodeReq) *kongchuanhujiao.Response {
	if err := sendCode(v.ID); err != nil {
		return kongchuanhujiao.GenerateErrResp(1, err.Error())
	}
	return kongchuanhujiao.DefaultSuccResp
}

// ====================================================================================================================

// PostLoginReq 登录验证 请求结构
type PostLoginReq struct {
	ID   string // 标识号
	Code string // 验证码
}

// PostLogin 登录验证 APIs。
// 调用方法：POST apis/accounts/login
func (a *APIs) PostLogin(v *PostLoginReq) *kongchuanhujiao.Response {

	if v.Code != account.GetCode(v.ID) || v.Code == "" { // FIXME datahub 鉴权
		return kongchuanhujiao.GenerateErrResp(1, "验证码有误")
	}

	now := time.Now()
	t, err := jwt.NewTokenWithClaims(jwt.SigningMethodES256, jwt.MapClaims{
		"iss": config.GetJWTConf().Iss,
		"sub": v.ID,
		"exp": now.AddDate(0, 1, 0).Unix(),
		"nbf": now.Unix(),
		"iat": now.Unix(),
	}).SignedString(config.GetJWTConf().Key)
	if err != nil {
		logger.Error("生成 JWT Token 失败", zap.Error(err))
		return kongchuanhujiao.DefaultErrResp
	}

	return &kongchuanhujiao.Response{Message: t}
}
