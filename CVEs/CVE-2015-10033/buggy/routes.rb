Rails.application.routes.draw do
  resources :resources

	root to: "static_pages#root"

 	resources :users, only: [:new, :create, :show]
	resource :session, only: [:new, :create, :destroy]
#   resources :coursesinstructors, only: [:create, :destroy]
# 	resources :coursesstudents, only: [:create, :destroy], controller: "courses_students"
#   resources :courses, only: [:create, :index, :destroy, :new, :show]

  namespace :api, defaults: { format: :json } do
    resources :coursesinstructors, only: [:create, :destroy]
    resources :coursesstudents, only: [:create, :destroy], controller: "courses_students"
    resources :announcements #might have to "member do" for easy-access custom routes from a particular course
    resources :assignments
    resources :resources
    resources :courses do
      get "course_search", on: :collection
    end
    resources :users, only: [:show, :index] do
      get "users_search", on: :collection
      resources :grades, only: [:index]
    end
  end

end
