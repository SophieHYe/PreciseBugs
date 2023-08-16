from rest_framework.status import HTTP_400_BAD_REQUEST, HTTP_403_FORBIDDEN
from rest_framework.views import APIView

from backend.response import FormattedResponse
from config import config
from backend.permissions import AdminOrAnonymousReadOnly


class ConfigView(APIView):
    throttle_scope = "config"
    permission_classes = (AdminOrAnonymousReadOnly,)

    def get(self, request, name=None):
        if name is None:
            if request.user.is_superuser:
                return FormattedResponse(config.get_all())
            return FormattedResponse(config.get_all_non_sensitive())
        if not config.is_sensitive(name) or request.is_superuser:
            return FormattedResponse(config.get(name))
        return FormattedResponse(status=HTTP_403_FORBIDDEN)

    def post(self, request, name):
        if "value" not in request.data:
            return FormattedResponse(status=HTTP_400_BAD_REQUEST)
        config.set(name, request.data.get("value"))
        return FormattedResponse()

    def patch(self, request, name):
        if "value" not in request.data:
            return FormattedResponse(status=HTTP_400_BAD_REQUEST)
        if config.get(name) is not None and isinstance(config.get(name), list):
            config.set("name", config.get(name).append(request.data["value"]))
            return FormattedResponse()
        config.set(name, request.data.get("value"))
        return FormattedResponse()
