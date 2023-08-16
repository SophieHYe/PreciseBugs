class Curupira::PasswordsController < ApplicationController
  before_filter :redirect_to_root_with_errors, if: :current_user

  def new
    @user = User.new
  end

  def create
    @user = User.find_by(params[:user])
    if @user.present?
      @user.deliver_reset_password_instructions!
      redirect_to new_session_path, notice: "Verifique seu email para receber instruções de recuperação de senha"
    else
      @user = User.new email: params[:user][:email]
      flash[:alert] = "Email não encontrado"
      render :new
    end
  end

  def edit
    @user = User.load_from_reset_password_token(params[:id])
    if @user.present?
      render :edit
    else
      redirect_to new_session_path, alert: "Token para resetar senha expirado ou inválido"
    end
  end

  def update
    @user = User.load_from_reset_password_token(params[:id])

    if @user
      @user.change_password!(params[:user][:password])
      Curupira::ResetPasswordMailer.reseted(@user).deliver_now
      redirect_to new_session_path, notice: "Senha atualizada com sucesso. Entre com sua nova senha"
    else
      redirect_to new_session_path, alert: "Token para resetar senha expirado ou inválido"
    end
  end

  private

  def redirect_to_root_with_errors
    redirect_to root_path, alert: "Você já está logado"
  end
end
