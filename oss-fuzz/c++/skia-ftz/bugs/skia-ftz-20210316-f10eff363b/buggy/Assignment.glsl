
out vec4 sk_FragColor;
uniform vec4 colorGreen;
struct S {
    float f;
    float af[5];
    vec4 h4;
    vec4 ah4[5];
};
vec4 main() {
    int i;
    i = 0;
    ivec4 i4;
    i4 = ivec4(1, 2, 3, 4);
    mat3 f3x3;
    f3x3 = mat3(1.0, 2.0, 3.0, 4.0, 5.0, 6.0, 7.0, 8.0, 9.0);
    vec4 x;
    x.w = 0.0;
    x.yx = vec2(0.0);
    int ai[1];
    ai[0] = 0;
    ivec4 ai4[1];
    ai4[0] = ivec4(1, 2, 3, 4);
    mat3 ah2x4[1];
    ah2x4[0] = mat3(1.0, 2.0, 3.0, 4.0, 5.0, 6.0, 7.0, 8.0, 9.0);
    vec4 af4[1];
    af4[0].x = 0.0;
    af4[0].ywxz = vec4(1.0);
    S s;
    s.f = 0.0;
    s.af[1] = 0.0;
    s.h4.zxy = vec3(9.0);
    s.ah4[2].yw = vec2(5.0);
    float l;
    float r;
    l = 0.0;
    ai[0] += ai4[0].x;
    s.f = 1.0;
    s.af[0] = 2.0;
    s.h4 = vec4(1.0);
    s.ah4[0] = vec4(2.0);
    af4[0] *= ah2x4[0][0].x;
    i4.y *= i;
    x.y *= l;
    s.f *= l;
    return colorGreen;
}
