<template>
  <auth-container>
    <div v-if="$vuetify.display.smAndDown" class="text-center">
      <v-icon size="64" icon="$ecamp" />
    </div>

    <h1 class="text-h4 text-center my-4">
      {{ $t('global.button.login') }}
    </h1>

    <v-alert
      v-if="error"
      class="mt-2 mb-4"
      variant="tonal"
      border="start"
      type="error"
      icon="mdi-alert"
    >
      <span class="d-block">{{ error }}</span>
    </v-alert>

    <v-btn
      block
      :color="authenticationInProgress ? 'blue-lighten-4' : 'blue-darken-2'"
      :size="$vuetify.display.smAndUp && 'large'"
      height="50"
      variant="outlined"
      class="my-4 pa-2 ec-login-button"
      :disabled="authenticationInProgress"
      @click="loginOidc"
    >
      <v-progress-circular v-if="authenticationInProgress" indeterminate size="24" />
      <img
        v-else
        :src="oidcProviderIcon"
        alt=""
        class="ec-oidc-provider-icon"
        height="24"
      />
      <v-spacer />
      <span>{{ $t('global.button.login') }}</span>
      <v-spacer />
      <icon-spacer />
    </v-btn>
  </auth-container>
</template>

<script>
import { isLoggedIn } from '@/plugins/auth'
import { componentI18n } from '@/plugins/i18n/index.js'
import AuthContainer from '@/components/layout/AuthContainer.vue'
import IconSpacer from '@/components/layout/IconSpacer.vue'
import { serverErrorToString } from '@/helpers/serverError'
import oidcProviderIcon from '@/assets/oidc-provider.webp'

export default {
  name: 'Login',
  components: {
    IconSpacer,
    AuthContainer,
  },
  beforeRouteEnter(to, from, next) {
    if (isLoggedIn()) {
      next(to.query.redirect || '/')
    } else {
      next()
    }
  },
  data() {
    return {
      error: null,
      authenticationInProgress: false,
      oidcProviderIcon,
    }
  },
  head() {
    return {
      title: this.$t('global.button.login'),
    }
  },
  mounted() {
    const navigatorLanguage = navigator.language
    if (componentI18n.availableLocales.includes(navigatorLanguage)) {
      this.$store.commit('setLanguage', navigatorLanguage)
    }
  },
  methods: {
    async loginOidc() {
      this.authenticationInProgress = true
      this.error = null
      try {
        await this.$auth.loginOidc()
      } catch (e) {
        this.authenticationInProgress = false
        this.error = serverErrorToString(e)
      }
    },
  },
}
</script>

<style lang="scss" scoped>
/* eslint-disable-next-line vue-scoped-css/no-unused-selector */
.ec-login-button.v-btn--disabled {
  color: rgba(0, 0, 0, 0.26) !important;
  opacity: 1;
}

.ec-login-button :deep(.v-btn__overlay) {
  opacity: 0.08;
}

.ec-login-button :deep(.v-btn__content) {
  width: 100%;
}

.ec-oidc-provider-icon {
  width: 24px;
  height: 24px;
  object-fit: contain;
}
</style>
