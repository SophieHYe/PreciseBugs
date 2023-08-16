var XINGApi        = require('xing-api'),
    mongoose       = require('mongoose'),
    Wall           = mongoose.model('Wall'),
    Profile        = mongoose.model('Profile'),
    xingApi        = new XINGApi({
      consumerKey: process.env.XING_CONSUMER_KEY,
      consumerSecret: process.env.XING_CONSUMER_SECRET,
      oauthCallback: process.env.OAUTH_CALLBACK
    });

module.exports = function (app, io) {
  app.get('/walls/:wall_id/connect', function (req, res) {
    // XXX ugly hack
    var existingAuthorizeCallback = xingApi.oauth._authorize_callback;
    xingApi.oauth._authorize_callback = existingAuthorizeCallback + '?wall_id=' + req.params.wall_id;

    xingApi.getRequestToken(function (oauthToken, oauthTokenSecret, authorizeUrl) {
      res.cookie('requestToken',
        JSON.stringify({ token: oauthToken, secret: oauthTokenSecret }),
        { signed: true });

      res.redirect(authorizeUrl);
    });

    // XXX ugly hack
    xingApi.oauth._authorize_callback = existingAuthorizeCallback;
  });

  app.get('/oauth_callback', function (req, res) {
    if (!req.signedCookies.requestToken) {
      return res.redirect("/");
    }

    var requestToken = JSON.parse(req.signedCookies.requestToken);

    xingApi.getAccessToken(requestToken.token, requestToken.secret, req.query.oauth_verifier,
      function (error, oauthToken, oauthTokenSecret) {
        if (error) {
          console.log(error);
          res.render('error');
          return;
        }
        req.session.regenerate(function (err) {
          res.cookie('requestToken', null); // delete cookie

          var client = xingApi.client(oauthToken, oauthTokenSecret);

          client.get('/v1/users/me', function (error, response) {
            var user = JSON.parse(response).users[0];

            Wall.findOne({ _id: req.query.wall_id }).exec()
              .then(function (wall) {

                var profile = new Profile({
                  userId: user.id,
                  displayName: user.display_name,
                  photoUrls: {
                    size_128x128: user.photo_urls.size_128x128,
                    size_256x256: user.photo_urls.size_256x256
                  }
                }).toObject();

                delete profile._id; // make sure that we don't overwrite the internal _id on an update

                Profile.findOneAndUpdate({ userId: user.id }, profile, { upsert: true }).exec()
                  .then(function (profile) {
                    wall.profiles.pull(profile._id);
                    wall.profiles.push(profile._id);

                    wall.save(function (err) {
                      if (err) {
                        console.error(err);
                        res.render('error');
                      } else {
                        req.session.user = {
                          id: profile._id,
                          oauthToken: oauthToken,
                          oauthTokenSecret: oauthTokenSecret
                        };

                        io.emit('profiles:updated');
                        res.render('oauth/callback', { url: "/walls/" + req.query.wall_id });
                      }
                    });
                  });
              }, function (err) {
                console.log(err);
              });
          });
        });
      });
  });
};
